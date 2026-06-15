<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\SalesQuotation;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CrmNotificationService;
use App\Services\FinanceCalculatorService;
use App\Services\FinanceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JobCardController extends Controller
{
    public function index(Request $request): View
    {
        $jobCards = JobCard::query()
            ->visibleTo($request->user())
            ->with(['salesQuotation', 'client', 'department', 'creator', 'invoice'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('job_card_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('salesQuotation', fn ($quotation) => $quotation->where('quotation_number', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('job_cards.index', [
            'jobCards' => $jobCards,
            'statuses' => JobCard::STATUSES,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request, FinanceNumberService $numbers): View|RedirectResponse
    {
        $quotation = null;

        if ($request->filled('sales_quotation_id')) {
            $quotation = SalesQuotation::query()
                ->visibleTo($request->user())
                ->with(['client', 'department'])
                ->findOrFail($request->integer('sales_quotation_id'));

            $this->authorizeQuotationWork($request, $quotation);

            if ($quotation->status !== SalesQuotation::STATUS_ACCEPTED) {
                return redirect()
                    ->route('sales-quotations.show', $quotation)
                    ->with('warning', 'Mark the quotation as client accepted before creating a job card.');
            }

            $existingJobCard = $quotation->jobCards()
                ->whereNotIn('status', [JobCard::STATUS_CANCELLED])
                ->first();

            if ($existingJobCard) {
                return redirect()
                    ->route('job-cards.show', $existingJobCard)
                    ->with('warning', 'This quotation already has an active job card.');
            }
        }

        return view('job_cards.create', [
            'jobCard' => new JobCard([
                'job_card_number' => $numbers->jobCardNumber(),
                'sales_quotation_id' => $quotation?->id,
                'client_id' => $quotation?->client_id,
                'department_id' => $quotation?->department_id ?: $request->user()->department_id,
                'title' => $quotation?->title,
                'status' => JobCard::STATUS_DRAFT,
                'start_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
            ]),
            'quotation' => $quotation,
            'quotations' => $this->availableQuotations($request),
            'staff' => $this->staffFor($request, $quotation?->department_id),
        ]);
    }

    public function store(Request $request, FinanceNumberService $numbers, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $quotation = SalesQuotation::query()
            ->visibleTo($request->user())
            ->findOrFail((int) $data['sales_quotation_id']);

        $this->authorizeQuotationWork($request, $quotation);

        if ($quotation->status !== SalesQuotation::STATUS_ACCEPTED) {
            return redirect()
                ->route('sales-quotations.show', $quotation)
                ->with('warning', 'Mark the quotation as client accepted before creating a job card.');
        }

        $existingJobCard = $quotation->jobCards()
            ->whereNotIn('status', [JobCard::STATUS_CANCELLED])
            ->first();

        if ($existingJobCard) {
            return redirect()
                ->route('job-cards.show', $existingJobCard)
                ->with('warning', 'This quotation already has an active job card.');
        }

        $data['job_card_number'] = $data['job_card_number'] ?: $numbers->jobCardNumber();
        $data['client_id'] = $quotation->client_id;
        $data['department_id'] = $quotation->department_id;
        $data['created_by'] = $request->user()->id;
        $data['status'] = JobCard::STATUS_DRAFT;
        $data['delivery_required'] = (bool) ($data['delivery_required'] ?? false);

        $jobCard = JobCard::query()->create($data);
        $audit->record('created', $jobCard, "Job card {$jobCard->job_card_number} created from quotation {$quotation->quotation_number}.");

        return redirect()->route('job-cards.show', $jobCard)->with('success', 'Job card created.');
    }

    public function show(Request $request, JobCard $jobCard): View
    {
        $this->authorizeView($request, $jobCard);

        return view('job_cards.show', [
            'jobCard' => $jobCard->load([
                'salesQuotation.items',
                'client.contacts',
                'department',
                'creator',
                'assignee',
                'invoice',
                'deliveryNotes.creator',
                'deliveryNotes.issuer',
            ]),
            'vatRate' => app(FinanceCalculatorService::class)->vatRatePercent(),
        ]);
    }

    public function edit(Request $request, JobCard $jobCard): View
    {
        $this->authorizeMutation($request, $jobCard);
        $this->abortIfLocked($jobCard);

        return view('job_cards.edit', [
            'jobCard' => $jobCard->load(['salesQuotation', 'client', 'department']),
            'quotation' => $jobCard->salesQuotation,
            'staff' => $this->staffFor($request, $jobCard->department_id),
        ]);
    }

    public function update(Request $request, JobCard $jobCard, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeMutation($request, $jobCard);
        $this->abortIfLocked($jobCard);

        $data = $this->validated($request, $jobCard);
        unset($data['sales_quotation_id'], $data['job_card_number']);
        $data['delivery_required'] = (bool) ($data['delivery_required'] ?? false);

        $jobCard->update($data);
        $audit->record('updated', $jobCard, "Job card {$jobCard->job_card_number} updated.");

        return redirect()->route('job-cards.show', $jobCard)->with('success', 'Job card updated.');
    }

    public function destroy(Request $request, JobCard $jobCard, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeMutation($request, $jobCard);
        $this->abortIfLocked($jobCard);

        $audit->record('deleted', $jobCard, "Job card {$jobCard->job_card_number} deleted.");
        $jobCard->delete();

        return redirect()->route('job-cards.index')->with('success', 'Job card deleted.');
    }

    public function markInProgress(Request $request, JobCard $jobCard, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeMutation($request, $jobCard);

        if (! in_array($jobCard->status, [JobCard::STATUS_DRAFT, JobCard::STATUS_IN_PROGRESS], true)) {
            abort(403);
        }

        $jobCard->update(['status' => JobCard::STATUS_IN_PROGRESS]);
        $audit->record('updated', $jobCard, "Job card {$jobCard->job_card_number} marked in progress.");

        return back()->with('success', 'Job card marked in progress.');
    }

    public function markCompleted(Request $request, JobCard $jobCard, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeMutation($request, $jobCard);

        if (! in_array($jobCard->status, [JobCard::STATUS_DRAFT, JobCard::STATUS_IN_PROGRESS, JobCard::STATUS_COMPLETED], true)) {
            abort(403);
        }

        $jobCard->update([
            'status' => JobCard::STATUS_COMPLETED,
            'completed_at' => $jobCard->completed_at ?: now(),
        ]);
        $audit->record('completed', $jobCard, "Job card {$jobCard->job_card_number} completed.");

        return back()->with('success', 'Job card marked complete.');
    }

    public function markReadyForInvoice(Request $request, JobCard $jobCard, CrmNotificationService $notifications, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeMutation($request, $jobCard);

        if (! in_array($jobCard->status, [JobCard::STATUS_DRAFT, JobCard::STATUS_IN_PROGRESS, JobCard::STATUS_COMPLETED, JobCard::STATUS_READY_FOR_INVOICE], true)) {
            abort(403);
        }

        $jobCard->update([
            'status' => JobCard::STATUS_READY_FOR_INVOICE,
            'completed_at' => $jobCard->completed_at ?: now(),
            'invoice_ready_at' => $jobCard->invoice_ready_at ?: now(),
        ]);

        $notifications->notifyUsers(
            User::query()
                ->where('is_active', true)
                ->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_RECEPTION])
                ->get(),
            'job_card_ready_for_invoice',
            "Job card {$jobCard->job_card_number} is ready for invoice",
            $jobCard->title,
            route('job-cards.show', $jobCard),
        );
        $audit->record('submitted', $jobCard, "Job card {$jobCard->job_card_number} marked ready for invoice.");

        return back()->with('success', 'Reception has been notified that this job card is ready for invoice.');
    }

    public function createInvoice(Request $request, JobCard $jobCard, FinanceNumberService $numbers, FinanceCalculatorService $calculator, AuditLogService $audit): RedirectResponse
    {
        if (! $request->user()->canManageFinance()) {
            abort(403);
        }

        $jobCard->load(['salesQuotation.items', 'invoice']);

        if ($jobCard->invoice) {
            return redirect()->route('invoices.show', $jobCard->invoice)->with('warning', 'This job card already has an invoice.');
        }

        if (! in_array($jobCard->status, [JobCard::STATUS_COMPLETED, JobCard::STATUS_READY_FOR_INVOICE], true)) {
            return back()->with('warning', 'The department must complete or mark the job card ready before reception creates an invoice.');
        }

        $invoice = DB::transaction(function () use ($request, $jobCard, $numbers, $calculator): Invoice {
            $quotation = $jobCard->salesQuotation;

            $invoice = Invoice::query()->create([
                'client_id' => $jobCard->client_id,
                'sales_quotation_id' => $jobCard->sales_quotation_id,
                'job_card_id' => $jobCard->id,
                'department_id' => $jobCard->department_id,
                'created_by' => $request->user()->id,
                'invoice_number' => $numbers->invoiceNumber(),
                'status' => Invoice::STATUS_DRAFT,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'notes' => $jobCard->notes ?: $quotation->notes,
                'terms' => 'Payment due within 30 days from invoice date.',
            ]);

            $calculator->syncInvoiceItems($invoice, $calculator->quotationItemsForInvoice($quotation));

            $jobCard->update(['status' => JobCard::STATUS_INVOICED]);
            $quotation->update([
                'status' => SalesQuotation::STATUS_CONVERTED,
                'converted_at' => now(),
            ]);

            return $invoice;
        });

        $audit->record('created', $invoice, "Invoice {$invoice->invoice_number} created from job card {$jobCard->job_card_number}.");

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice drafted from job card.');
    }

    public function cancel(Request $request, JobCard $jobCard, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeMutation($request, $jobCard);

        if ($jobCard->invoice) {
            return back()->with('warning', 'A job card with an invoice cannot be cancelled.');
        }

        $jobCard->update(['status' => JobCard::STATUS_CANCELLED]);
        $audit->record('cancelled', $jobCard, "Job card {$jobCard->job_card_number} cancelled.");

        return back()->with('success', 'Job card cancelled.');
    }

    private function validated(Request $request, ?JobCard $jobCard = null): array
    {
        return $request->validate([
            'sales_quotation_id' => [$jobCard ? 'nullable' : 'required', 'exists:sales_quotations,id'],
            'job_card_number' => ['nullable', 'string', 'max:40', \Illuminate\Validation\Rule::unique('job_cards', 'job_card_number')->ignore($jobCard)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'delivery_required' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function availableQuotations(Request $request)
    {
        return SalesQuotation::query()
            ->visibleTo($request->user())
            ->where('status', SalesQuotation::STATUS_ACCEPTED)
            ->whereDoesntHave('jobCards', fn ($query) => $query->whereNotIn('status', [JobCard::STATUS_CANCELLED]))
            ->with(['client', 'department'])
            ->latest('accepted_at')
            ->get();
    }

    private function staffFor(Request $request, ?int $departmentId)
    {
        return User::query()
            ->where('is_active', true)
            ->when(! $request->user()->canManageFinance(), fn ($query) => $query->where('department_id', $request->user()->department_id))
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('name')
            ->get();
    }

    private function authorizeView(Request $request, JobCard $jobCard): void
    {
        if ($request->user()->canViewReports() || $jobCard->department_id === $request->user()->department_id) {
            return;
        }

        abort(403);
    }

    private function authorizeMutation(Request $request, JobCard $jobCard): void
    {
        if ($request->user()->canManageFinance() || $jobCard->department_id === $request->user()->department_id) {
            return;
        }

        abort(403);
    }

    private function authorizeQuotationWork(Request $request, SalesQuotation $quotation): void
    {
        if ($request->user()->canManageFinance() || $quotation->department_id === $request->user()->department_id) {
            return;
        }

        abort(403);
    }

    private function abortIfLocked(JobCard $jobCard): void
    {
        if (in_array($jobCard->status, [JobCard::STATUS_INVOICED, JobCard::STATUS_CANCELLED], true)) {
            abort(403);
        }
    }
}
