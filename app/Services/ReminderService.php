<?php

namespace App\Services;

use App\Mail\DeadlineReminderMail;
use App\Models\Assignment;
use App\Models\Quotation;
use App\Models\TenderProposal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ReminderService
{
    public const TENDER_REMINDER_DAYS_BEFORE = 5;

    public const QUOTATION_REMINDER_DAYS_BEFORE = 1;

    public const QUOTATION_REMINDER_HOURS_BEFORE = 24;

    public const DAYS_BEFORE = self::TENDER_REMINDER_DAYS_BEFORE;

    public const DASHBOARD_WINDOW_DAYS = 5;

    public function __construct(private readonly LocalMailMirror $localMailMirror) {}

    public function dueItems(): Collection
    {
        return $this->upcomingItems(self::DASHBOARD_WINDOW_DAYS)
            ->filter(fn (array $item): bool => match ($item['type']) {
                'Tender Proposal' => (int) $item['days_left'] === self::TENDER_REMINDER_DAYS_BEFORE,
                'Quotation' => (int) $item['days_left'] === self::QUOTATION_REMINDER_DAYS_BEFORE,
                default => false,
            })
            ->values();
    }

    public function upcomingItems(?int $daysAhead = null): Collection
    {
        $daysAhead ??= self::DASHBOARD_WINDOW_DAYS;
        $items = collect();

        Assignment::query()
            ->whereIn('workflow_status', ['Assigned', 'In Progress'])
            ->whereIn('assignable_type', [TenderProposal::class, Quotation::class])
            ->whereDoesntHave('submissions')
            ->with(['assignable', 'department'])
            ->orderBy('due_date')
            ->get()
            ->each(function (Assignment $assignment) use ($items, $daysAhead): void {
                $record = $assignment->assignable;

                if (! $record || $this->isClosedRecord($record)) {
                    return;
                }

                $dueOn = $this->dueDateFor($assignment, $record);

                if ($dueOn->greaterThan(now()->addDays($daysAhead))) {
                    return;
                }

                $isTender = $record instanceof TenderProposal;
                $reference = $isTender ? $record->tender_reference : $record->quotation_code;
                $title = $isTender ? $record->title : $record->opportunity;
                $type = $isTender ? 'Tender Proposal' : 'Quotation';
                $reminderDaysBefore = $isTender
                    ? self::TENDER_REMINDER_DAYS_BEFORE
                    : self::QUOTATION_REMINDER_DAYS_BEFORE;
                $reminderNote = $isTender
                    ? 'Tender proposal reminders are sent 5 days before the due date.'
                    : 'Quotation reminders are sent 24 hours before the due date.';
                $daysLeft = (int) now()->startOfDay()->diffInDays($dueOn, false);

                $items->push([
                    'type' => $type,
                    'model' => $record,
                    'assignment' => $assignment,
                    'department' => $assignment->department?->name ?? 'Assigned Department',
                    'reference' => $reference,
                    'title' => $title,
                    'owner' => $assignment->assignee_name,
                    'owner_email' => $assignment->assignee_email,
                    'status' => $record->status,
                    'priority' => $record->priority,
                    'due_label' => $assignment->due_date ? 'Assignment Due Date' : ($isTender ? 'Closing Date' : 'Valid Until'),
                    'due_on' => $dueOn,
                    'days_left' => $daysLeft,
                    'reminder_days_before' => $reminderDaysBefore,
                    'reminder_note' => $reminderNote,
                    'subject' => $this->subjectFor($type, $reference, $daysLeft),
                    'portal_url' => $isTender
                        ? route('tender-proposals.show', $record)
                        : route('quotations.show', $record),
                ]);
            });

        return $items->sortBy('due_on')->values();
    }

    public function markOverdueQuotations(): int
    {
        $count = 0;

        $this->upcomingItems(self::DASHBOARD_WINDOW_DAYS)
            ->filter(fn (array $item): bool => $item['model'] instanceof Quotation && (int) $item['days_left'] < 0)
            ->pluck('model')
            ->unique('id')
            ->each(function (Quotation $quotation) use (&$count): void {
                if (in_array($quotation->status, ['Accepted', 'Rejected', 'Expired', Quotation::STATUS_OVERDUE], true)) {
                    return;
                }

                $quotation->update(['status' => Quotation::STATUS_OVERDUE]);
                $count++;
            });

        return $count;
    }

    public function sendDueReminders(bool $markOverdue = true): int
    {
        if ($markOverdue) {
            $this->markOverdueQuotations();
        }

        $sent = 0;

        foreach ($this->dueItems() as $item) {
            if ($this->sendReminder($item)) {
                $sent++;
            }
        }

        return $sent;
    }

    private function sendReminder(array $item): bool
    {
        $model = $item['model'];
        $existing = $model->reminderLogs()
            ->where('assignment_id', $item['assignment']->id)
            ->whereDate('due_on', $item['due_on']->toDateString())
            ->where('days_before', $item['reminder_days_before'])
            ->first();

        if ($existing) {
            return false;
        }

        $subject = $item['subject'];
        try {
            if (! $item['owner_email']) {
                throw new \RuntimeException('Assigned department email is missing.');
            }

            Mail::to($item['owner_email'])->send(new DeadlineReminderMail($item));
            $this->localMailMirror->sendMailable($item['owner_email'], new DeadlineReminderMail($item));

            $status = 'Sent';
            $message = '';
        } catch (Throwable $exception) {
            $status = 'Failed';
            $message = $exception->getMessage();
        }

        $model->reminderLogs()->create([
            'assignment_id' => $item['assignment']->id,
            'due_on' => $item['due_on'],
            'days_before' => $item['reminder_days_before'],
            'recipient' => $item['owner_email'],
            'status' => $status,
            'message' => $message,
            'sent_at' => now(),
        ]);

        $model->emailLogs()->create([
            'category' => 'reminder',
            'recipient' => $item['owner_email'] ?: '',
            'subject' => $subject,
            'status' => $status,
            'message' => $message,
            'sent_at' => now(),
        ]);

        return $status === 'Sent';
    }

    private function dueDateFor(Assignment $assignment, TenderProposal|Quotation $record)
    {
        return $assignment->due_date ?: ($record instanceof TenderProposal ? $record->closing_date : $record->valid_until);
    }

    private function isClosedRecord(TenderProposal|Quotation $record): bool
    {
        if ($record instanceof TenderProposal) {
            return in_array($record->status, ['Closed', 'Cancelled', 'Finished Submitted'], true);
        }

        return in_array($record->status, ['Accepted', 'Rejected', 'Expired', 'Finished Submitted'], true);
    }

    private function subjectFor(string $type, string $reference, int $daysLeft): string
    {
        if ($daysLeft < 0) {
            return "Overdue: {$type} {$reference} needs a response";
        }

        if ($type === 'Quotation') {
            return "Reminder: Quotation {$reference} is due in 24 hours";
        }

        return "Reminder: Tender Proposal {$reference} is due in {$daysLeft} days";
    }
}
