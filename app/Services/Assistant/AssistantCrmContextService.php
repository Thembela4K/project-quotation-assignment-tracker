<?php

namespace App\Services\Assistant;

use App\Models\Client;
use App\Models\CrmNotification;
use App\Models\CrmTask;
use App\Models\DeliveryNote;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\Quotation;
use App\Models\Requisition;
use App\Models\SalesQuotation;
use App\Models\Supplier;
use App\Models\TenderProposal;
use App\Models\User;

class AssistantCrmContextService
{
    private const RECORD_SECTIONS = [
        'clients',
        'suppliers',
        'tender_proposals',
        'quotation_requests',
        'sales_quotations',
        'job_cards',
        'delivery_notes',
        'invoices',
        'requisitions',
        'tasks',
        'documents',
        'notifications',
    ];

    public function __construct(private readonly AssistantAccessService $access)
    {
    }

    public function build(User $user): array
    {
        return $this->buildScoped($user, self::RECORD_SECTIONS, 'full');
    }

    /**
     * @param  array<int, string>  $sections
     */
    public function buildScoped(User $user, array $sections, string $mode = 'scoped'): array
    {
        $limit = (int) config('services.assistant_ai.context_record_limit', 120);
        $sections = array_values(array_intersect(self::RECORD_SECTIONS, array_unique($sections)));

        return [
            ...$this->baseContext($user),
            'context_mode' => $mode,
            'records' => $this->records($user, $sections, $limit),
        ];
    }

    public function buildLight(User $user): array
    {
        return [
            ...$this->baseContext($user),
            'context_mode' => 'light',
            'records' => [],
        ];
    }

    private function baseContext(User $user): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'department' => $user->department?->name,
                'can_view_reports' => $user->canViewReports(),
                'can_manage_finance' => $user->canManageFinance(),
                'can_approve_finance' => $user->canApproveFinance(),
                'can_view_requisitions' => $user->canViewRequisitions(),
            ],
            'supported_actions' => [
                'navigate' => [
                    'modules' => [
                        'dashboard',
                        'clients',
                        'suppliers',
                        'documents',
                        'tender_proposals',
                        'quotation_requests',
                        'requisitions',
                        'tasks',
                        'sales_quotations',
                        'job_cards',
                        'delivery_notes',
                        'invoices',
                        'approvals',
                        'attendance',
                        'reports',
                        'notifications',
                    ],
                    'note' => 'Use navigate only when the user clearly asks to open, show, list, view, or go to a CRM area. For count/fact questions, answer directly with action null.',
                ],
            ],
            'counts' => $this->counts($user),
        ];
    }

    /**
     * @param  array<int, string>  $sections
     */
    private function records(User $user, array $sections, int $limit): array
    {
        $records = [];

        foreach ($sections as $section) {
            $records[$section] = match ($section) {
                'clients' => $this->clients($limit),
                'suppliers' => $this->suppliers($user, $limit),
                'tender_proposals' => $this->tenderProposals($user, $limit),
                'quotation_requests' => $this->quotationRequests($user, $limit),
                'sales_quotations' => $this->salesQuotations($user, $limit),
                'job_cards' => $this->jobCards($user, $limit),
                'delivery_notes' => $this->deliveryNotes($user, $limit),
                'invoices' => $this->invoices($user, $limit),
                'requisitions' => $this->requisitions($user, $limit),
                'tasks' => $this->tasks($user, $limit),
                'documents' => $this->documents($user, $limit),
                'notifications' => $this->notifications($user, $limit),
            };
        }

        return $records;
    }

    private function counts(User $user): array
    {
        return [
            'clients' => Client::query()->count(),
            'suppliers' => Supplier::query()->visibleTo($user)->count(),
            'tender_proposals' => TenderProposal::query()->visibleTo($user)->count(),
            'quotation_requests' => Quotation::query()->visibleTo($user)->count(),
            'sales_quotations' => SalesQuotation::query()->visibleTo($user)->count(),
            'job_cards' => JobCard::query()->visibleTo($user)->count(),
            'job_cards_ready_for_invoice' => JobCard::query()->visibleTo($user)->where('status', JobCard::STATUS_READY_FOR_INVOICE)->count(),
            'delivery_notes' => DeliveryNote::query()->visibleTo($user)->count(),
            'invoices' => Invoice::query()->visibleTo($user)->count(),
            'unpaid_invoices' => Invoice::query()->visibleTo($user)->where('balance_due', '>', 0)->count(),
            'requisitions' => Requisition::query()->visibleTo($user)->count(),
            'tasks' => CrmTask::query()->visibleTo($user)->count(),
            'documents' => $this->documentQuery($user)->count(),
            'unread_notifications' => CrmNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count(),
            'pending_approvals' => $user->canViewReports()
                ? SalesQuotation::query()->where('status', SalesQuotation::STATUS_SUBMITTED)->count()
                    + Requisition::query()->whereIn('status', [Requisition::STATUS_SUBMITTED, Requisition::STATUS_IN_REVIEW])->count()
                : 0,
        ];
    }

    private function clients(int $limit): array
    {
        return Client::query()
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'client_code', 'name', 'email', 'phone', 'billing_email', 'vat_number', 'city', 'country', 'is_active'])
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'code' => $client->client_code,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'billing_email' => $client->billing_email,
                'vat_number' => $client->vat_number,
                'location' => trim(collect([$client->city, $client->country])->filter()->join(', ')),
                'active' => $client->is_active,
            ])
            ->all();
    }

    private function suppliers(User $user, int $limit): array
    {
        return Supplier::query()
            ->visibleTo($user)
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'supplier_code', 'name', 'contact_person', 'email', 'phone', 'vat_number', 'is_active'])
            ->map(fn (Supplier $supplier): array => [
                'id' => $supplier->id,
                'code' => $supplier->supplier_code,
                'name' => $supplier->name,
                'contact_person' => $supplier->contact_person,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'vat_number' => $supplier->vat_number,
                'active' => $supplier->is_active,
            ])
            ->all();
    }

    private function tenderProposals(User $user, int $limit): array
    {
        return TenderProposal::query()
            ->visibleTo($user)
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'tender_reference', 'title', 'status', 'priority', 'received_date', 'closing_date', 'brief'])
            ->map(fn (TenderProposal $tender): array => [
                'id' => $tender->id,
                'reference' => $tender->tender_reference,
                'title' => $tender->title,
                'status' => $tender->status,
                'priority' => $tender->priority,
                'received_date' => $tender->received_date?->toDateString(),
                'closing_date' => $tender->closing_date?->toDateString(),
                'brief' => str($tender->brief)->limit(240)->toString(),
            ])
            ->all();
    }

    private function quotationRequests(User $user, int $limit): array
    {
        return Quotation::query()
            ->visibleTo($user)
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'quotation_code', 'client', 'opportunity', 'status', 'priority', 'quoted_amount', 'issue_date', 'valid_until', 'notes'])
            ->map(fn (Quotation $quotation): array => [
                'id' => $quotation->id,
                'code' => $quotation->quotation_code,
                'client' => $quotation->client,
                'opportunity' => $quotation->opportunity,
                'status' => $quotation->status,
                'priority' => $quotation->priority,
                'quoted_amount' => $quotation->quoted_amount,
                'issue_date' => $quotation->issue_date?->toDateString(),
                'valid_until' => $quotation->valid_until?->toDateString(),
                'notes' => str($quotation->notes)->limit(240)->toString(),
            ])
            ->all();
    }

    private function salesQuotations(User $user, int $limit): array
    {
        return SalesQuotation::query()
            ->visibleTo($user)
            ->with(['client:id,name', 'department:id,name'])
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (SalesQuotation $quotation): array => [
                'id' => $quotation->id,
                'number' => $quotation->quotation_number,
                'title' => $quotation->title,
                'client' => $quotation->client?->name,
                'department' => $quotation->department?->name,
                'status' => $quotation->status,
                'valid_until' => $quotation->valid_until?->toDateString(),
                'total' => $quotation->total,
            ])
            ->all();
    }

    private function invoices(User $user, int $limit): array
    {
        return Invoice::query()
            ->visibleTo($user)
            ->with(['client:id,name', 'department:id,name', 'salesQuotation:id,quotation_number', 'jobCard:id,job_card_number'])
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->invoice_number,
                'quotation_number' => $invoice->salesQuotation?->quotation_number,
                'job_card_number' => $invoice->jobCard?->job_card_number,
                'client' => $invoice->client?->name,
                'department' => $invoice->department?->name,
                'status' => $invoice->status,
                'issue_date' => $invoice->issue_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'total' => $invoice->total,
                'amount_paid' => $invoice->amount_paid,
                'balance_due' => $invoice->balance_due,
            ])
            ->all();
    }

    private function jobCards(User $user, int $limit): array
    {
        return JobCard::query()
            ->visibleTo($user)
            ->with(['salesQuotation:id,quotation_number', 'client:id,name', 'department:id,name', 'invoice:id,job_card_id,invoice_number'])
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (JobCard $jobCard): array => [
                'id' => $jobCard->id,
                'number' => $jobCard->job_card_number,
                'title' => $jobCard->title,
                'quotation_number' => $jobCard->salesQuotation?->quotation_number,
                'invoice_number' => $jobCard->invoice?->invoice_number,
                'client' => $jobCard->client?->name,
                'department' => $jobCard->department?->name,
                'status' => $jobCard->status,
                'delivery_required' => $jobCard->delivery_required,
                'due_date' => $jobCard->due_date?->toDateString(),
            ])
            ->all();
    }

    private function deliveryNotes(User $user, int $limit): array
    {
        return DeliveryNote::query()
            ->visibleTo($user)
            ->with(['jobCard:id,job_card_number', 'salesQuotation:id,quotation_number', 'client:id,name', 'department:id,name'])
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (DeliveryNote $deliveryNote): array => [
                'id' => $deliveryNote->id,
                'number' => $deliveryNote->delivery_note_number,
                'job_card_number' => $deliveryNote->jobCard?->job_card_number,
                'quotation_number' => $deliveryNote->salesQuotation?->quotation_number,
                'client' => $deliveryNote->client?->name,
                'department' => $deliveryNote->department?->name,
                'status' => $deliveryNote->status,
                'delivery_date' => $deliveryNote->delivery_date?->toDateString(),
                'recipient' => $deliveryNote->recipient_name,
            ])
            ->all();
    }

    private function requisitions(User $user, int $limit): array
    {
        return Requisition::query()
            ->visibleTo($user)
            ->with(['department:id,name', 'supplier:id,name'])
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Requisition $requisition): array => [
                'id' => $requisition->id,
                'number' => $requisition->requisition_number,
                'title' => $requisition->title,
                'department' => $requisition->department?->name,
                'supplier' => $requisition->supplier?->name,
                'category' => $requisition->category,
                'priority' => $requisition->priority,
                'status' => $requisition->status,
                'needed_by' => $requisition->needed_by?->toDateString(),
                'estimated_total' => $requisition->estimated_total,
            ])
            ->all();
    }

    private function tasks(User $user, int $limit): array
    {
        return CrmTask::query()
            ->visibleTo($user)
            ->with(['department:id,name', 'assignee:id,name'])
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (CrmTask $task): array => [
                'id' => $task->id,
                'number' => $task->task_number,
                'title' => $task->title,
                'department' => $task->department?->name,
                'assignee' => $task->assignee?->name,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
            ])
            ->all();
    }

    private function documents(User $user, int $limit): array
    {
        return $this->documentQuery($user)
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'title' => $document->title,
                'name' => $document->original_name,
                'category' => $document->category,
                'mime_type' => $document->mime_type,
                'tags' => $document->tags,
                'created_at' => $document->created_at?->toDateString(),
                'indexed_text' => str($document->textIndex?->content)->limit(800)->toString(),
            ])
            ->all();
    }

    private function notifications(User $user, int $limit): array
    {
        return CrmNotification::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'type', 'title', 'body', 'action_url', 'read_at', 'created_at'])
            ->map(fn (CrmNotification $notification): array => [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'body' => $notification->body,
                'action_url' => $notification->action_url,
                'read' => filled($notification->read_at),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function documentQuery(User $user)
    {
        $ids = Document::query()
            ->with('textIndex')
            ->get()
            ->filter(fn (Document $document): bool => $this->access->canAccessDocument($user, $document))
            ->pluck('id');

        return Document::query()
            ->with('textIndex')
            ->whereIn('id', $ids);
    }
}
