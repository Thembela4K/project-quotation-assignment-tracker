<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class LocalMailMirror
{
    /**
     * @param  string|array<int, string>  $recipients
     */
    public function sendMailable(string|array $recipients, Mailable $mailable): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            Mail::mailer('mailpit')->to($recipients)->send($mailable);
        } catch (Throwable $exception) {
            Log::warning('Local Mailpit mirror failed.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  string|array<int, string>  $recipients
     * @param  array<string, mixed>  $data
     */
    public function sendView(string|array $recipients, string $view, array $data, string $subject): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            Mail::mailer('mailpit')->send($view, $data, function ($mail) use ($recipients, $subject): void {
                $mail->to($recipients)->subject($subject);
            });
        } catch (Throwable $exception) {
            Log::warning('Local Mailpit mirror failed.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function enabled(): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        $mailpit = config('mail.mailers.mailpit');

        if (! is_array($mailpit) || blank($mailpit['host'] ?? null) || blank($mailpit['port'] ?? null)) {
            return false;
        }

        $default = config('mail.default');

        if ($default === 'mailpit') {
            return false;
        }

        $defaultMailer = config("mail.mailers.{$default}");

        return ! (
            is_array($defaultMailer)
            && ($defaultMailer['host'] ?? null) === $mailpit['host']
            && (int) ($defaultMailer['port'] ?? 0) === (int) $mailpit['port']
        );
    }
}
