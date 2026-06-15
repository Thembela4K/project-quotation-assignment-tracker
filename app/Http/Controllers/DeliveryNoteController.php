<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Models\Department;
use App\Models\JobCard;
use App\Services\AuditLogService;
use App\Services\FinanceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeliveryNoteController extends Controller
{
    public function index(Request $request): View
    {
        $deliveryNotes = DeliveryNote::query()
            ->visibleTo($request->user())
            ->with(['jobCard', 'salesQuotation', 'client', 'department', 'creator', 'issuer'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('delivery_note_number', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('jobCard', fn ($jobCard) => $jobCard->where('job_card_number', 'like', "%{$search}%"))
                        ->orWhereHas('salesQuotation', fn ($quotation) => $quotation->where('quotation_number', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('delivery_notes.index', [
            'deliveryNotes' => $deliveryNotes,
            'statuses' => DeliveryNote::STATUSES,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request, FinanceNumberService $numbers): View
    {
        $this->authorizeManage($request);

        $jobCard = null;
        if ($request->filled('job_card_id')) {
            $jobCard = JobCard::query()
                ->visibleTo($request->user())
                ->with(['salesQuotation', 'client', 'department'])
                ->findOrFail($request->integer('job_card_id'));
        }

        return view('delivery_notes.create', [
            'deliveryNote' => new DeliveryNote([
                'delivery_note_number' => $numbers->deliveryNoteNumber(),
                'job_card_id' => $jobCard?->id,
                'delivery_date' => now()->toDateString(),
                'delivery_address' => $jobCard?->client?->address,
                'status' => DeliveryNote::STATUS_DRAFT,
            ]),
            'jobCard' => $jobCard,
            'jobCards' => $this->availableJobCards(),
        ]);
    }

    public function store(Request $request, FinanceNumberService $numbers, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validated($request);
        $jobCard = JobCard::query()
            ->with(['salesQuotation', 'client', 'department'])
            ->findOrFail((int) $data['job_card_id']);

        $data['delivery_note_number'] = $data['delivery_note_number'] ?: $numbers->deliveryNoteNumber();
        $data['sales_quotation_id'] = $jobCard->sales_quotation_id;
        $data['client_id'] = $jobCard->client_id;
        $data['department_id'] = $jobCard->department_id;
        $data['created_by'] = $request->user()->id;
        $data['status'] = DeliveryNote::STATUS_DRAFT;

        $deliveryNote = DeliveryNote::query()->create($data);
        $audit->record('created', $deliveryNote, "Delivery note {$deliveryNote->delivery_note_number} created for job card {$jobCard->job_card_number}.");

        return redirect()->route('delivery-notes.show', $deliveryNote)->with('success', 'Delivery note created.');
    }

    public function show(Request $request, DeliveryNote $deliveryNote): View
    {
        $this->authorizeView($request, $deliveryNote);

        return view('delivery_notes.show', [
            'deliveryNote' => $deliveryNote->load(['jobCard', 'salesQuotation', 'client.contacts', 'department', 'creator', 'issuer']),
        ]);
    }

    public function edit(Request $request, DeliveryNote $deliveryNote): View
    {
        $this->authorizeManage($request);

        if (! in_array($deliveryNote->status, [DeliveryNote::STATUS_DRAFT, DeliveryNote::STATUS_ISSUED], true)) {
            abort(403);
        }

        return view('delivery_notes.edit', [
            'deliveryNote' => $deliveryNote->load(['jobCard', 'client']),
            'jobCard' => $deliveryNote->jobCard,
            'jobCards' => $this->availableJobCards(),
        ]);
    }

    public function update(Request $request, DeliveryNote $deliveryNote, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeManage($request);

        if (! in_array($deliveryNote->status, [DeliveryNote::STATUS_DRAFT, DeliveryNote::STATUS_ISSUED], true)) {
            abort(403);
        }

        $data = $this->validated($request, $deliveryNote);
        unset($data['job_card_id'], $data['delivery_note_number']);

        $deliveryNote->update($data);
        $audit->record('updated', $deliveryNote, "Delivery note {$deliveryNote->delivery_note_number} updated.");

        return redirect()->route('delivery-notes.show', $deliveryNote)->with('success', 'Delivery note updated.');
    }

    public function destroy(Request $request, DeliveryNote $deliveryNote, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeManage($request);

        if ($deliveryNote->status !== DeliveryNote::STATUS_DRAFT) {
            abort(403);
        }

        $audit->record('deleted', $deliveryNote, "Delivery note {$deliveryNote->delivery_note_number} deleted.");
        $deliveryNote->delete();

        return redirect()->route('delivery-notes.index')->with('success', 'Delivery note deleted.');
    }

    public function issue(Request $request, DeliveryNote $deliveryNote, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeManage($request);

        $deliveryNote->update([
            'status' => DeliveryNote::STATUS_ISSUED,
            'issued_by' => $request->user()->id,
            'issued_at' => $deliveryNote->issued_at ?: now(),
        ]);
        $audit->record('issued', $deliveryNote, "Delivery note {$deliveryNote->delivery_note_number} issued.");

        return back()->with('success', 'Delivery note issued.');
    }

    public function markDelivered(Request $request, DeliveryNote $deliveryNote, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeManage($request);

        $deliveryNote->update([
            'status' => DeliveryNote::STATUS_DELIVERED,
            'delivered_at' => $deliveryNote->delivered_at ?: now(),
        ]);
        $audit->record('delivered', $deliveryNote, "Delivery note {$deliveryNote->delivery_note_number} marked delivered.");

        return back()->with('success', 'Delivery note marked delivered.');
    }

    public function cancel(Request $request, DeliveryNote $deliveryNote, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeManage($request);

        $deliveryNote->update(['status' => DeliveryNote::STATUS_CANCELLED]);
        $audit->record('cancelled', $deliveryNote, "Delivery note {$deliveryNote->delivery_note_number} cancelled.");

        return back()->with('success', 'Delivery note cancelled.');
    }

    public function print(Request $request, DeliveryNote $deliveryNote): View
    {
        $this->authorizeView($request, $deliveryNote);

        return view('delivery_notes.print', [
            'deliveryNote' => $deliveryNote->load(['jobCard', 'salesQuotation', 'client.contacts', 'department', 'creator', 'issuer']),
        ]);
    }

    private function validated(Request $request, ?DeliveryNote $deliveryNote = null): array
    {
        return $request->validate([
            'job_card_id' => [$deliveryNote ? 'nullable' : 'required', 'exists:job_cards,id'],
            'delivery_note_number' => ['nullable', 'string', 'max:40', Rule::unique('delivery_notes', 'delivery_note_number')->ignore($deliveryNote)],
            'delivery_date' => ['nullable', 'date'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:80'],
            'delivery_address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function availableJobCards()
    {
        return JobCard::query()
            ->whereIn('status', [JobCard::STATUS_IN_PROGRESS, JobCard::STATUS_COMPLETED, JobCard::STATUS_READY_FOR_INVOICE, JobCard::STATUS_INVOICED])
            ->with(['client', 'salesQuotation'])
            ->latest()
            ->get();
    }

    private function authorizeManage(Request $request): void
    {
        if (! $request->user()->canManageFinance()) {
            abort(403);
        }
    }

    private function authorizeView(Request $request, DeliveryNote $deliveryNote): void
    {
        if ($request->user()->canViewReports() || $deliveryNote->department_id === $request->user()->department_id) {
            return;
        }

        abort(403);
    }
}
