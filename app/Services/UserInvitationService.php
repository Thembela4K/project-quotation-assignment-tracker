<?php

namespace App\Services;

use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class UserInvitationService
{
    public function invite(User $user, ?User $inviter): UserInvitation
    {
        return DB::transaction(function () use ($user, $inviter): UserInvitation {
            $user->invitations()
                ->where('status', UserInvitation::STATUS_PENDING)
                ->update(['status' => UserInvitation::STATUS_EXPIRED]);

            $token = Str::random(48);
            $invitation = $user->invitations()->create([
                'invited_by' => $inviter?->id,
                'email' => (string) $user->email,
                'token_hash' => $this->hashToken($token),
                'status' => UserInvitation::STATUS_PENDING,
                'expires_at' => now()->addDays(7),
            ]);

            $user->forceFill([
                'invited_by' => $inviter?->id,
                'invited_at' => now(),
                'invitation_accepted_at' => null,
            ])->save();

            $this->send($user, $invitation, $token);

            return $invitation->fresh();
        });
    }

    public function resend(User $user, ?User $inviter): UserInvitation
    {
        return $this->invite($user, $inviter);
    }

    public function findPendingByToken(string $token): ?UserInvitation
    {
        return UserInvitation::query()
            ->with(['user.department'])
            ->where('token_hash', $this->hashToken($token))
            ->where('status', UserInvitation::STATUS_PENDING)
            ->first();
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function send(User $user, UserInvitation $invitation, string $token): void
    {
        try {
            Mail::to($user->email)->send(new UserInvitationMail($user, $invitation, $token));

            $invitation->update([
                'status' => UserInvitation::STATUS_PENDING,
                'sent_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $invitation->update([
                'status' => UserInvitation::STATUS_EMAIL_FAILED,
                'last_error' => $exception->getMessage(),
            ]);
        }
    }
}
