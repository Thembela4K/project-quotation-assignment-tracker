<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserInvitation;
use App\Services\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function show(string $token, UserInvitationService $invitations): View|RedirectResponse
    {
        $invitation = $invitations->findPendingByToken($token);

        if (! $invitation || ! $invitation->isUsable()) {
            $invitation?->update(['status' => UserInvitation::STATUS_EXPIRED]);

            return redirect()->route('login')->with('warning', 'This invitation link is invalid or has expired. Ask an administrator to resend it.');
        }

        return view('auth.accept-invitation', [
            'token' => $token,
            'invitation' => $invitation,
            'user' => $invitation->user,
        ]);
    }

    public function accept(Request $request, string $token, UserInvitationService $invitations): RedirectResponse
    {
        $invitation = $invitations->findPendingByToken($token);

        if (! $invitation || ! $invitation->isUsable()) {
            $invitation?->update(['status' => UserInvitation::STATUS_EXPIRED]);

            return redirect()->route('login')->with('warning', 'This invitation link is invalid or has expired. Ask an administrator to resend it.');
        }

        $user = $invitation->user;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->forceFill([
            'name' => $data['name'],
            'username' => $data['username'] ?: $this->uniqueUsername($data['name'], $user),
            'password' => $data['password'],
            'is_active' => true,
            'email_verified_at' => now(),
            'invitation_accepted_at' => now(),
        ])->save();

        $invitation->update([
            'status' => UserInvitation::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        $user->invitations()
            ->whereKeyNot($invitation->id)
            ->where('status', UserInvitation::STATUS_PENDING)
            ->update(['status' => UserInvitation::STATUS_EXPIRED]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Your account is active.');
    }

    private function uniqueUsername(string $name, User $user): string
    {
        $base = Str::slug($name, '.');
        $base = preg_replace('/[^a-z0-9.]/', '', strtolower($base)) ?: 'staff';
        $username = $base;
        $counter = 2;

        while (User::query()->where('username', $username)->whereKeyNot($user->id)->exists()) {
            $username = "{$base}{$counter}";
            $counter++;
        }

        return $username;
    }
}
