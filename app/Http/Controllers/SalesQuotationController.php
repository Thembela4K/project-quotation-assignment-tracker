<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\Department;
use App\Models\SalesQuotation;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CrmNotificationService;
use App\Services\FinanceCalculatorService;
use App\Services\FinanceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesQuotationController extends Controller
{
    public function index(Request $request): View
    {
        $quotations = SalesQuotation::query()
            ->visibleTo($request->user())
            ->with(['client', 'department', 'creator', 'approver'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('quotation_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('issue_date')
            ->paginate(12)
            ->withQueryString();

        return view('sales_quotations.index', [
            'salesQuotations' => $quotations,
            'statuses' => SalesQuotation::STATUSES,
        ]);
    }

    public function create(Request $request, FinanceNumberService $numbers): View
    {
        $this->authorizeDraft($request);

        return view('sales_quotations.create', [
            'salesQuotation' => new SalesQuotation([
                'quotation_number' => $numbers->salesQuotationNumber(),
                'department_id' => $request->user()->department_id,
                'status' => SalesQuotation::STATUS_DRAFT,
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(30)->toDateString(),
                'terms' => 'Prices are valid until the quotation expiry date. Payment terms to be confirmed on invoice.',
            ]),
            'clients' => $this->clients(),
            'departments' => $this->departmentsFor($request),
            'catalogItems' => $this->catalogItemsFor($request),
            'vatRate' => app(FinanceCalculatorService::class)->vatRatePercent(),
        ]);
    }

    public function store(Request $request, FinanceNumberService $numbers, FinanceCalculatorService $calculator): RedirectResponse
    {
        $this->authorizeDraft($request);

        $data = $this->validated($request);
        $data['quotation_number'] = $data['quotation_number'] ?: $numbers->salesQuotationNumber();
        $data['created_by'] = $request->user()->id;
        $data['department_id'] = $request->user()->canManageFinance() ? $data['department_id'] : $request->user()->department_id;
        $items = $this->validatedItems($request);

        $salesQuotation = DB::transaction(function () use ($data, $items, $calculator): SalesQuotation {
            $quotation = SalesQuotation::query()->create($data);
            $calculator->syncSalesQuotationItems($quotation, $items);

            return $quotation;
        });

        return redirect()->route('sales-quotations.show', $salesQuotation)->with('success', 'Sales quotation drafted.');
    }

    public function show(Request $request, SalesQuotation $salesQuotation): View
    {
        $this->authorizeView($request, $salesQuotation);

        return view('sales_quotations.show', [
            'salesQuotation' => $salesQuotation->load([
                'client.contacts',
                'department',
                'creator',
                'approver',
                'items.catalogItem',
                'invoice',
                'jobCards.invoice',
                'jobCards.deliveryNotes',
                'deliveryNotes',
            ]),
            'vatRate' => app(FinanceCalculatorService::class)->vatRatePercent(),
        ]);
    }

    public function edit(Request $request, SalesQuotation $salesQuotation): View
    {
        $this->authorizeEdit($request, $salesQuotation);

        return view('sales_quotations.edit', [
            'salesQuotation' => $salesQuotation->load('items'),
            'clients' => $this->clients(),
            'departments' => $this->departmentsFor($request),
            'catalogItems' => $this->catalogItemsFor($request),
            'vatRate' => app(FinanceCalculatorService::class)->vatRatePercent(),
        ]);
    }

    public function update(Request $request, SalesQuotation $salesQuotation, FinanceCalculatorService $calculator): RedirectResponse
    {
        $this->authorizeEdit($request, $salesQuotation);

        $data = $this->validated($request, $salesQuotation);
        $data['department_id'] = $request->user()->canManageFinance() ? $data['department_id'] : $request->user()->department_id;
        $items = $this->validatedItems($request);

        DB::transaction(function () use ($salesQuotation, $data, $items, $calculator): void {
            $salesQuotation->update($data);
            $calculator->syncSalesQuotationItems($salesQuotation, $items);
        });

        return redirect()->route('sales-quotations.show', $salesQuotation)->with('success', 'Sales quotation updated.');
    }

    public function destroy(Request $request, SalesQuotation $salesQuotation): RedirectResponse
    {
        $this->authorizeEdit($request, $salesQuotation);
        $salesQuotation->delete();

        return redirect()->route('sales-quotations.index')->with('success', 'Sales quotation deleted.');
    }

    public function submit(Request $request, SalesQuotation $salesQuotation, CrmNotificationService $notifications, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeEdit($request, $salesQuotation);

        $salesQuotation->update([
            'status' => SalesQuotation::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'approval_notes' => null,
        ]);
        $notifications->notifyApprovers('sales_quotation_approval', "Sales quotation {$salesQuotation->quotation_number} needs approval", $salesQuotation->title, route('sales-quotations.show', $salesQuotation));
        $audit->record('submitted', $salesQuotation, "Sales quotation {$salesQuotation->quotation_number} submitted for approval.");

        return back()->with('success', 'Sales quotation submitted for director approval.');
    }

    public function approve(Request $request, SalesQuotation $salesQuotation, CrmNotificationService $notifications, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeApproval($request);

        $data = $request->validate(['approval_notes' => ['nullable', 'string']]);
        $salesQuotation->update([
            'status' => SalesQuotation::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approval_notes' => $data['approval_notes'] ?? null,
            'approved_at' => now(),
            'rejected_at' => null,
        ]);
        $this->notifyQuotationOwner($salesQuotation, $notifications, 'sales_quotation_approved', "Sales quotation {$salesQuotation->quotation_number} approved");
        $audit->record('approved', $salesQuotation, "Sales quotation {$salesQuotation->quotation_number} approved.");

        return back()->with('success', 'Sales quotation approved.');
    }

    public function reject(Request $request, SalesQuotation $salesQuotation, CrmNotificationService $notifications, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeApproval($request);

        $data = $request->validate(['approval_notes' => ['required', 'string']]);
        $salesQuotation->update([
            'status' => SalesQuotation::STATUS_REJECTED,
            'approved_by' => $request->user()->id,
            'approval_notes' => $data['approval_notes'],
            'rejected_at' => now(),
            'approved_at' => null,
        ]);
        $this->notifyQuotationOwner($salesQuotation, $notifications, 'sales_quotation_rejected', "Sales quotation {$salesQuotation->quotation_number} rejected");
        $audit->record('rejected', $salesQuotation, "Sales quotation {$salesQuotation->quotation_number} rejected.");

        return back()->with('success', 'Sales quotation rejected with notes.');
    }

    public function markSent(Request $request, SalesQuotation $salesQuotation): RedirectResponse
    {
        $this->authorizeExternalTracking($request, $salesQuotation);

        if (! in_array($salesQuotation->status, [SalesQuotation::STATUS_APPROVED, SalesQuotation::STATUS_SENT], true)) {
            abort(403);
        }

        $salesQuotation->update([
            'status' => SalesQuotation::STATUS_SENT,
            'sent_at' => $salesQuotation->sent_at ?: now(),
        ]);

        return back()->with('success', 'Sales quotation marked as sent externally.');
    }

    public function markAccepted(Request $request, SalesQuotation $salesQuotation): RedirectResponse
    {
        $this->authorizeExternalTracking($request, $salesQuotation);

        if (! in_array($salesQuotation->status, [SalesQuotation::STATUS_APPROVED, SalesQuotation::STATUS_SENT, SalesQuotation::STATUS_ACCEPTED], true)) {
            abort(403);
        }

        $salesQuotation->update([
            'status' => SalesQuotation::STATUS_ACCEPTED,
            'sent_at' => $salesQuotation->sent_at ?: now(),
            'accepted_at' => $salesQuotation->accepted_at ?: now(),
        ]);

        return back()->with('success', 'Sales quotation marked as accepted by client.');
    }

    public function markDeclined(Request $request, SalesQuotation $salesQuotation): RedirectResponse
    {
        $this->authorizeExternalTracking($request, $salesQuotation);

        if (! in_array($salesQuotation->status, [SalesQuotation::STATUS_APPROVED, SalesQuotation::STATUS_SENT, SalesQuotation::STATUS_DECLINED], true)) {
            abort(403);
        }

        $salesQuotation->update(['status' => SalesQuotation::STATUS_DECLINED]);

        return back()->with('success', 'Sales quotation marked as declined by client.');
    }

    public function markExpired(Request $request, SalesQuotation $salesQuotation): RedirectResponse
    {
        $this->authorizeExternalTracking($request, $salesQuotation);

        if (! in_array($salesQuotation->status, [SalesQuotation::STATUS_APPROVED, SalesQuotation::STATUS_SENT, SalesQuotation::STATUS_EXPIRED], true)) {
            abort(403);
        }

        $salesQuotation->update(['status' => SalesQuotation::STATUS_EXPIRED]);

        return back()->with('success', 'Sales quotation marked as expired.');
    }

    public function convertToInvoice(Request $request, SalesQuotation $salesQuotation): RedirectResponse
    {
        if (! $request->user()->canManageFinance()) {
            abort(403);
        }

        if ($salesQuotation->invoice) {
            return redirect()->route('invoices.show', $salesQuotation->invoice)->with('warning', 'This quotation already has an invoice.');
        }

        return redirect()
            ->route('sales-quotations.show', $salesQuotation)
            ->with('warning', 'Invoices are now created from completed job cards. Ask the department to create and complete a job card first.');
    }

    public function print(Request $request, SalesQuotation $salesQuotation): View
    {
        $this->authorizeView($request, $salesQuotation);

        return view('sales_quotations.print', [
            'salesQuotation' => $salesQuotation->load(['client.contacts', 'department', 'items']),
            'vatRate' => app(FinanceCalculatorService::class)->vatRatePercent(),
        ]);
    }

    private function validated(Request $request, ?SalesQuotation $salesQuotation = null): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'quotation_number' => ['nullable', 'string', 'max:40', Rule::unique('sales_quotations', 'quotation_number')->ignore($salesQuotation)],
            'title' => ['required', 'string', 'max:255'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
        ]) + [
            'status' => $salesQuotation?->status ?: SalesQuotation::STATUS_DRAFT,
        ];
    }

    private function validatedItems(Request $request): array
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.catalog_item_id' => ['nullable', 'exists:catalog_items,id'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.taxable' => ['nullable', 'boolean'],
        ]);

        return collect($data['items'])
            ->filter(fn (array $item): bool => trim((string) ($item['description'] ?? '')) !== '')
            ->map(fn (array $item): array => $item + ['discount_amount' => 0, 'taxable' => false])
            ->values()
            ->all();
    }

    private function clients()
    {
        return Client::query()->where('is_active', true)->orderBy('name')->get();
    }

    private function departmentsFor(Request $request)
    {
        if (! $request->user()->canManageFinance()) {
            return Department::query()->whereKey($request->user()->department_id)->get();
        }

        return Department::query()->where('is_active', true)->orderBy('name')->get();
    }

    private function catalogItemsFor(Request $request)
    {
        return CatalogItem::query()->visibleTo($request->user())->where('is_active', true)->orderBy('name')->get();
    }

    private function authorizeView(Request $request, SalesQuotation $quotation): void
    {
        if ($request->user()->canViewReports() || $quotation->department_id === $request->user()->department_id) {
            return;
        }

        abort(403);
    }

    private function authorizeDraft(Request $request): void
    {
        if (! $request->user()->canDraftFinance()) {
            abort(403);
        }
    }

    private function authorizeEdit(Request $request, SalesQuotation $quotation): void
    {
        if (! in_array($quotation->status, [SalesQuotation::STATUS_DRAFT, SalesQuotation::STATUS_REJECTED], true)) {
            abort(403);
        }

        if ($request->user()->canManageFinance() || $quotation->department_id === $request->user()->department_id) {
            return;
        }

        abort(403);
    }

    private function authorizeExternalTracking(Request $request, SalesQuotation $quotation): void
    {
        if ($request->user()->canManageFinance() || $quotation->department_id === $request->user()->department_id) {
            return;
        }

        abort(403);
    }

    private function authorizeApproval(Request $request): void
    {
        if (! $request->user()->canApproveFinance()) {
            abort(403);
        }
    }

    private function notifyQuotationOwner(SalesQuotation $quotation, CrmNotificationService $notifications, string $type, string $title): void
    {
        $quotation->loadMissing(['creator', 'department']);

        if ($quotation->creator) {
            $notifications->notifyUser($quotation->creator, $type, $title, $quotation->title, route('sales-quotations.show', $quotation));
        }

        $notifications->notifyDepartment($quotation->department, $type, $title, $quotation->title, route('sales-quotations.show', $quotation));
    }
}
