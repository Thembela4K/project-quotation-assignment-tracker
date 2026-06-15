<?php

namespace App\Services;

use App\Mail\AssignmentNotificationMail;
use App\Models\Assignment;
use App\Models\Document;
use App\Models\TenderProposal;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AssignmentEmailService
{
    public function __construct(private readonly LocalMailMirror $localMailMirror) {}

    public function send(Assignment $assignment): string
    {
        $assignment->loadMissing(['assignable', 'department']);
        $assignable = $assignment->assignable;
        $reference = $assignable->tender_reference ?? $assignable->quotation_code;
        $title = $assignable->title ?? $assignable->opportunity;
        $dueLabel = $assignment->due_date ? 'Assignment Due Date' : ($assignable instanceof TenderProposal ? 'Closing Date' : 'Valid Until');
        $dueDate = $assignment->due_date ?: ($assignable instanceof TenderProposal ? $assignable->closing_date : $assignable->valid_until);
        $portalUrl = $assignable instanceof TenderProposal
            ? route('tender-proposals.show', $assignable)
            : route('quotations.show', $assignable);
        $importantDates = $assignable instanceof TenderProposal
            ? $assignable->importantDates()->get()->map(fn ($date): string => "{$date->label}: {$date->due_date->toDateString()}")->all()
            : [];
        $documents = $assignable->documents()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Document $document): array => [
                'name' => $document->original_name,
                'category' => Document::CATEGORIES[$document->category] ?? 'Document',
                'download_url' => route('documents.download', $document),
            ])
            ->all();
        $subject = "Assignment: {$reference} routed to {$assignment->department->name}";

        try {
            $this->localMailMirror->sendMailable($assignment->assignee_email, new AssignmentNotificationMail(
                assignment: $assignment,
                recordType: $assignable instanceof TenderProposal ? 'Tender Proposal' : 'Quotation',
                reference: $reference,
                title: $title,
                status: $assignable->status,
                priority: $assignable->priority,
                dueLabel: $dueLabel,
                dueDate: $dueDate->toDateString(),
                portalUrl: $portalUrl,
                importantDates: $importantDates,
                documentCount: $assignable->documents()->count(),
                documents: $documents,
            ));
            Mail::to($assignment->assignee_email)->send(new AssignmentNotificationMail(
                assignment: $assignment,
                recordType: $assignable instanceof TenderProposal ? 'Tender Proposal' : 'Quotation',
                reference: $reference,
                title: $title,
                status: $assignable->status,
                priority: $assignable->priority,
                dueLabel: $dueLabel,
                dueDate: $dueDate->toDateString(),
                portalUrl: $portalUrl,
                importantDates: $importantDates,
                documentCount: $assignable->documents()->count(),
                documents: $documents,
            ));

            $status = 'Assignment Email Sent';
            $message = '';
        } catch (Throwable $exception) {
            $status = 'Assignment Email Failed';
            $message = $exception->getMessage();
        }

        $assignable->emailLogs()->create([
            'category' => 'assignment',
            'recipient' => $assignment->assignee_email,
            'subject' => $subject,
            'status' => $status === 'Assignment Email Sent' ? 'Sent' : 'Failed',
            'message' => $message,
            'sent_at' => now(),
        ]);

        $assignment->update(['status' => $status]);

        return $status;
    }
}
