<?php

namespace App\Services;

use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class PasswordResetOtpService
{
    public const EXPIRES_IN_MINUTES = 10;

    public function send(User $user): ?PasswordResetOtp
    {
        if (! $this->canResetByEmail($user)) {
            return null;
        }

        $otp = (string) random_int(100000, 999999);
        $record = PasswordResetOtp::query()->create([
            'user_id' => $user->id,
            'email' => strtolower((string) $user->email),
            'code_hash' => $this->hashCode($otp),
            'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
        ]);

        Mail::to($user->email)->send(new PasswordResetOtpMail($user, $otp, self::EXPIRES_IN_MINUTES));

        return $record;
    }

    public function latestUsableFor(User $user): ?PasswordResetOtp
    {
        return PasswordResetOtp::query()
            ->where('user_id', $user->id)
            ->where('email', strtolower((string) $user->email))
            ->whereNull('used_at')
            ->latest()
            ->first();
    }

    public function codeMatches(PasswordResetOtp $otp, string $code): bool
    {
        return hash_equals($otp->code_hash, $this->hashCode($code));
    }

    public function hashCode(string $code): string
    {
        return hash('sha256', trim($code));
    }

    public function canResetByEmail(User $user): bool
    {
        return $user->is_active
            && filled($user->email)
            && filled($user->invited_at)
            && filled($user->invitation_accepted_at);
    }
}
