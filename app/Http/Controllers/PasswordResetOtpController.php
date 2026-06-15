<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class PasswordResetOtpController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendOtp(Request $request, PasswordResetOtpService $otps): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);
        $email = strtolower($data['email']);
        $user = User::query()
            ->where('email', $email)
            ->first();

        if ($user && $otps->canResetByEmail($user)) {
            try {
                $otps->send($user);
            } catch (Throwable $exception) {
                Log::warning('Password reset OTP email failed.', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('password.reset.form', ['email' => $email])
            ->with('success', 'If that invited email exists, a password reset code has been sent.');
    }

    public function resetForm(Request $request): View
    {
        return view('auth.reset-password', [
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request, PasswordResetOtpService $otps): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $email = strtolower($data['email']);
        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user || ! $otps->canResetByEmail($user)) {
            return back()
                ->withErrors(['otp' => 'The reset code could not be verified.'])
                ->withInput(['email' => $email]);
        }

        $otp = $otps->latestUsableFor($user);

        if (! $otp || ! $otp->isUsable()) {
            return back()
                ->withErrors(['otp' => 'The reset code is invalid or expired. Request a new code.'])
                ->withInput(['email' => $email]);
        }

        if (! $otps->codeMatches($otp, $data['otp'])) {
            $otp->increment('attempts');

            return back()
                ->withErrors(['otp' => 'The reset code is invalid or expired.'])
                ->withInput(['email' => $email]);
        }

        $user->forceFill(['password' => $data['password']])->save();
        $otp->update(['used_at' => now()]);

        return redirect()->route('login')->with('success', 'Your password has been reset. Sign in with the new password.');
    }
}
