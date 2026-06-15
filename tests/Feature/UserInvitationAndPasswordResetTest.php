<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpMail;
use App\Mail\UserInvitationMail;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserInvitationAndPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_invites_user_and_user_accepts_with_password(): void
    {
        Mail::fake();
        $admin = $this->user('Admin User', 'admin@example.com', User::ROLE_SUPER_ADMIN);
        $department = $this->department('MIS Department');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Invited Staff',
            'email' => 'invited@example.com',
            'role' => User::ROLE_DEPARTMENT_USER,
            'department_id' => $department->id,
        ])->assertRedirect(route('users.index'));

        $invitedUser = User::query()->where('email', 'invited@example.com')->firstOrFail();
        $this->assertFalse($invitedUser->is_active);
        $this->assertNotNull($invitedUser->invited_at);
        $this->assertNull($invitedUser->invitation_accepted_at);

        $token = null;
        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use (&$token): bool {
            $token = $mail->token;

            return $mail->hasTo('invited@example.com');
        });

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->get(route('invitations.accept', $token))->assertOk();
        $this->post(route('invitations.accept.store', $token), [
            'name' => 'Invited Staff',
            'username' => 'invited.staff',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('dashboard'));

        $invitedUser->refresh();
        $this->assertAuthenticatedAs($invitedUser);
        $this->assertTrue($invitedUser->is_active);
        $this->assertSame('invited.staff', $invitedUser->username);
        $this->assertNotNull($invitedUser->invitation_accepted_at);
    }

    public function test_authenticated_user_can_change_own_password(): void
    {
        $user = $this->user('Portal User', 'portal@example.com', User::ROLE_DEPARTMENT_USER);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'changed-password',
            'password_confirmation' => 'changed-password',
        ])->assertRedirect();

        auth()->logout();

        $this->post(route('login.store'), [
            'login' => $user->email,
            'password' => 'changed-password',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_invited_user_can_reset_password_with_email_otp(): void
    {
        Mail::fake();
        $user = $this->user('Reset User', 'reset@example.com', User::ROLE_DEPARTMENT_USER);

        $this->post(route('password.email'), [
            'email' => 'reset@example.com',
        ])->assertRedirect(route('password.reset.form', ['email' => 'reset@example.com']));

        $otp = null;
        Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) use (&$otp): bool {
            $otp = $mail->otp;

            return $mail->hasTo('reset@example.com');
        });

        $this->post(route('password.reset'), [
            'email' => 'reset@example.com',
            'otp' => $otp,
            'password' => 'reset-password',
            'password_confirmation' => 'reset-password',
        ])->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'login' => 'reset@example.com',
            'password' => 'reset-password',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_uninvited_email_does_not_receive_password_reset_otp(): void
    {
        Mail::fake();
        User::query()->create([
            'name' => 'Manual User',
            'email' => 'manual@example.com',
            'password' => 'password',
            'role' => User::ROLE_DEPARTMENT_USER,
            'is_active' => true,
        ]);

        $this->post(route('password.email'), [
            'email' => 'manual@example.com',
        ])->assertRedirect(route('password.reset.form', ['email' => 'manual@example.com']));

        Mail::assertNothingSent();
    }

    private function department(string $name): Department
    {
        return Department::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]);
    }

    private function user(string $name, string $email, string $role): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);
    }
}
