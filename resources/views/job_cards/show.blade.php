@extends('layouts.app')

@section('content')
    @php
        $canWork = auth()->user()->canManageFinance() || $jobCard->department_id === auth()->user()->department_id;
        $canInvoice = auth()->user()->canManageFinance() && in_array($jobCard->status, [\App\Models\JobCard::STATUS_COMPLETED, \App\Models\JobCard::STATUS_READY_FOR_INVOICE], true) && ! $jobCard->invoice;
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $jobCard->job_card_number }}</h1>
            <p class="page-subtitle">{{ $jobCard->title }} | {{ $jobCard->client->name }} | {{ $jobCard->status }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('sales-quotations.show', $jobCard->salesQuotation) }}">Quotation</a>
            @if($canWork && ! in_array($jobCard->status, [\App\Models\JobCard::STATUS_INVOICED, \App\Models\JobCard::STATUS_CANCELLED], true))
                <a class="btn-secondary" href="{{ route('job-cards.edit', $jobCard) }}">Edit</a>
            @endif
            @if($canWork && $jobCard->status === \App\Models\JobCard::STATUS_DRAFT)
                <form method="POST" action="{{ route('job-cards.in-progress', $jobCard) }}">@csrf<button class="btn-secondary" type="submit">Start Work</button></form>
            @endif
            @if($canWork && in_array($jobCard->status, [\App\Models\JobCard::STATUS_DRAFT, \App\Models\JobCard::STATUS_IN_PROGRESS], true))
                <form method="POST" action="{{ route('job-cards.complete', $jobCard) }}">@csrf<button class="btn-secondary" type="submit">Mark Complete</button></form>
            @endif
            @if($canWork && in_array($jobCard->status, [\App\Models\JobCard::STATUS_DRAFT, \App\Models\JobCard::STATUS_IN_PROGRESS, \App\Models\JobCard::STATUS_COMPLETED], true))
                <form method="POST" action="{{ route('job-cards.ready-for-invoice', $jobCard) }}">@csrf<button class="btn-primary" type="submit">Ready for Invoice</button></form>
            @endif
            @if(auth()->user()->canManageFinance() && $jobCard->delivery_required)
                <a class="btn-secondary" href="{{ route('delivery-notes.create', ['job_card_id' => $jobCard->id]) }}">Delivery Note</a>
            @endif
            @if($canInvoice)
                <form method="POST" action="{{ route('job-cards.create-invoice', $jobCard) }}">@csrf<button class="btn-primary" type="submit">Create Invoice</button></form>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_380px]">
        <section class="panel">
            <h2 class="section-title">Work Scope</h2>
            <div class="mt-4 whitespace-pre-line text-sm text-neutral-700">{{ $jobCard->scope ?: 'No scope captured yet.' }}</div>

            <h2 class="section-title mt-8">Quotation Lines</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>VAT</th><th>Total</th></tr></thead>
                    <tbody>
                        @foreach($jobCard->salesQuotation->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td>{{ number_format((float) $item->quantity, 2) }}</td>
                                <td>E{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td>E{{ number_format((float) $item->vat_amount, 2) }}</td>
                                <td>E{{ number_format((float) $item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="panel">
                <h2 class="section-title">Details</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="label">Department</dt><dd>{{ $jobCard->department?->name ?? 'Unassigned' }}</dd></div>
                    <div><dt class="label">Assigned Staff</dt><dd>{{ $jobCard->assignee?->name ?? 'Department team' }}</dd></div>
                    <div><dt class="label">Start Date</dt><dd>{{ $jobCard->start_date?->toFormattedDateString() ?? '-' }}</dd></div>
                    <div><dt class="label">Due Date</dt><dd>{{ $jobCard->due_date?->toFormattedDateString() ?? '-' }}</dd></div>
                    <div><dt class="label">Delivery Note Required</dt><dd>{{ $jobCard->delivery_required ? 'Yes' : 'No' }}</dd></div>
                    <div><dt class="label">Notes</dt><dd class="whitespace-pre-line">{{ $jobCard->notes ?: 'None' }}</dd></div>
                    @if($jobCard->invoice)
                        <div><dt class="label">Invoice</dt><dd><a class="link" href="{{ route('invoices.show', $jobCard->invoice) }}">{{ $jobCard->invoice->invoice_number }}</a></dd></div>
                    @endif
                </dl>
            </section>

            <section class="panel">
                <h2 class="section-title">Delivery Notes</h2>
                <div class="mt-4 divide-y divide-neutral-100">
                    @forelse($jobCard->deliveryNotes as $deliveryNote)
                        <div class="list-row">
                            <span><strong>{{ $deliveryNote->delivery_note_number }}</strong><small>{{ $deliveryNote->status }} | {{ $deliveryNote->delivery_date?->toFormattedDateString() ?? 'No date' }}</small></span>
                            <em><a class="link" href="{{ route('delivery-notes.show', $deliveryNote) }}">Open</a></em>
                        </div>
                    @empty
                        <p class="empty">No delivery notes yet.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
@endsection
