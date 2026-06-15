<?php

namespace App\Services;

use App\Models\Requisition;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class RequisitionEmailService
{
    public function __construct(private readonly LocalMailMirror $localMailMirror) {}

    public function notifySubmitted(Requisition $requisition): int
    {
        $requisition->load(['department', 'requester', 'items']);
        $subject = "Requisition {$requisition->requisition_number} submitted";

        return $this->sendToApprovers($requisition, $subject, 'Submitted', 'A requisition has been submitted for review and approval.');
    }

    public function notifyDecision(Requisition $requisition, string $eventLabel, string $message): int
    {
        $requisition->load(['department', 'requester', 'items']);
        $subject = "Requisition {$requisition->requisition_number}: {$eventLabel}";
        $recipients = $this->requesterRecipients($requisition);

        return $this->send($requisition, $recipients, $subject, $eventLabel, $message);
    }

    private function sendToApprovers(Requisition $requisition, string $subject, string $eventLabel, string $message): int
    {
        return $this->send($requisition, $this->approverRecipients(), $subject, $eventLabel, $message);
    }

    /**
     * @return Collection<int, string>
     */
    private function approverRecipients(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_DIRECTOR])
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function requesterRecipients(Requisition $requisition): Collection
    {
        return collect([
            $requisition->requester?->email,
            $requisition->department?->email,
        ])->filter()->unique()->values();
    }

    /**
     * @param  Collection<int, string>  $recipients
     */
    private function send(Requisition $requisition, Collection $recipients, string $subject, string $eventLabel, string $message): int
    {
        if ($recipients->isEmpty()) {
            $requisition->emailLogs()->create([
                'category' => 'requisition',
                'recipient' => '',
                'subject' => $subject,
                'status' => 'Failed',
                'message' => 'No eligible requisition email recipients were found.',
                'sent_at' => now(),
            ]);

            return 0;
        }

        $sent = 0;

        foreach ($recipients as $recipient) {
            try {
                $data = [
                    'requisition' => $requisition,
                    'eventLabel' => $eventLabel,
                    'messageText' => $message,
                    'portalUrl' => route('requisitions.show', $requisition),
                ];

                $this->localMailMirror->sendView($recipient, 'emails.requisition-notification', $data, $subject);
                Mail::send('emails.requisition-notification', $data, function ($mail) use ($recipient, $subject): void {
                    $mail->to($recipient)->subject($subject);
                });

                $sent++;
                $requisition->emailLogs()->create([
                    'category' => 'requisition',
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'status' => 'Sent',
                    'message' => $message,
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                $requisition->emailLogs()->create([
                    'category' => 'requisition',
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'status' => 'Failed',
                    'message' => $exception->getMessage(),
                    'sent_at' => now(),
                ]);
            }
        }

        return $sent;
    }
}
