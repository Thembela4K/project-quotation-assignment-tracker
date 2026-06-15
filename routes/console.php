<?php

use App\Mail\TestNotificationMail;
use App\Models\AttendanceRecord;
use App\Models\CrmTask;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Requisition;
use App\Models\SalesQuotation;
use App\Services\Assistant\DocumentTextExtractor;
use App\Services\ReminderService;
use App\Services\CrmNotificationService;
use App\Services\LocalMailMirror;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reminders:send-due', function (): int {
    $sent = app(ReminderService::class)->sendDueReminders();
    $this->info("{$sent} due reminder emails sent.");

    return self::SUCCESS;
})->purpose('Send due tender proposal and quotation reminders');

Artisan::command('mail:test {recipient?}', function (): int {
    $recipient = $this->argument('recipient') ?: config('mail.from.address');

    if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $this->error('Provide a valid recipient email address or configure MAIL_FROM_ADDRESS.');

        return self::FAILURE;
    }

    try {
        app(LocalMailMirror::class)->sendMailable($recipient, new TestNotificationMail);
        Mail::to($recipient)->send(new TestNotificationMail);
    } catch (Throwable $exception) {
        $this->error('Test email failed: '.$exception->getMessage());

        return self::FAILURE;
    }

    $this->info("Test email sent to {$recipient}.");

    return self::SUCCESS;
})->purpose('Send a branded development test email');

Artisan::command('crm:send-daily-notifications', function (): int {
    $notifications = app(CrmNotificationService::class);
    $count = 0;

    CrmTask::query()
        ->with('assignee')
        ->whereNotNull('assigned_to')
        ->whereNotIn('status', [CrmTask::STATUS_DONE, CrmTask::STATUS_CANCELLED])
        ->whereNotNull('due_date')
        ->whereDate('due_date', '<=', now()->addDay()->toDateString())
        ->get()
        ->each(function (CrmTask $task) use (&$count, $notifications): void {
            if ($task->assignee) {
                $notifications->notifyUser($task->assignee, 'task_due', "Task due: {$task->task_number}", $task->title, route('tasks.show', $task));
                $count++;
            }
        });

    SalesQuotation::query()
        ->where('status', SalesQuotation::STATUS_SUBMITTED)
        ->get()
        ->each(function (SalesQuotation $quotation) use (&$count, $notifications): void {
            $count += $notifications->notifyApprovers('sales_quotation_approval', "Sales quotation {$quotation->quotation_number} is pending approval", $quotation->title, route('sales-quotations.show', $quotation));
        });

    Requisition::query()
        ->whereIn('status', [Requisition::STATUS_SUBMITTED, Requisition::STATUS_IN_REVIEW, Requisition::STATUS_APPROVED])
        ->get()
        ->each(function (Requisition $requisition) use (&$count, $notifications): void {
            $count += $notifications->notifyApprovers('requisition_pending', "Requisition {$requisition->requisition_number} needs action", $requisition->title, route('requisitions.show', $requisition));
        });

    Invoice::query()
        ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
        ->whereDate('due_date', '<=', now()->toDateString())
        ->get()
        ->each(function (Invoice $invoice) use (&$count, $notifications): void {
            $count += $notifications->notifyApprovers('invoice_due', "Invoice {$invoice->invoice_number} is due or overdue", 'Outstanding balance E'.$invoice->balance_due, route('invoices.show', $invoice));
        });

    AttendanceRecord::query()
        ->with('user')
        ->whereDate('work_date', now()->subDay()->toDateString())
        ->whereNull('out_time')
        ->get()
        ->each(function (AttendanceRecord $record) use (&$count, $notifications): void {
            $count += $notifications->notifyApprovers('attendance_exception', "Attendance exception for {$record->user->name}", 'Clock-out is missing.', route('attendance.index'));
        });

    $this->info("{$count} CRM notifications created.");

    return self::SUCCESS;
})->purpose('Create daily CRM reminders and approval notifications');

Artisan::command('assistant:index-documents', function (DocumentTextExtractor $extractor): int {
    $count = 0;

    Document::query()
        ->whereDoesntHave('textIndex')
        ->orderBy('id')
        ->chunkById(50, function ($documents) use ($extractor, &$count): void {
            $documents->each(function (Document $document) use ($extractor, &$count): void {
                $extractor->index($document);
                $count++;
            });
        });

    $this->info("{$count} documents indexed for MIS.");

    return self::SUCCESS;
})->purpose('Index existing document text for MIS search');

Schedule::command('reminders:send-due')->dailyAt('08:00');
Schedule::command('crm:send-daily-notifications')->dailyAt('08:15');
