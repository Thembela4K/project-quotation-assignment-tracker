@extends('layouts.app')

@section('content')
    @php
        $canTrackOutcome = auth()->user()->canManageFinance() || $salesQuotation->department_id === auth()->user()->department_id;
        $canCreateJobCard = $canTrackOutcome && $salesQuotation->status === \App\Models\SalesQuotation::STATUS_ACCEPTED;
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $salesQuotation->quotation_number }}</h1>
            <p class="page-subtitle">{{ $salesQuotation->title }} | {{ $salesQuotation->client->name }} | {{ $salesQuotation->status }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('sales-quotations.print', $salesQuotation) }}" target="_blank">Print</a>
            <a class="btn-secondary" href="{{ route('sales-quotations.pdf', $salesQuotation) }}">PDF</a>
            @if(in_array($salesQuotation->status, [\App\Models\SalesQuotation::STATUS_DRAFT, \App\Models\SalesQuotation::STATUS_REJECTED], true) && (auth()->user()->canManageFinance() || $salesQuotation->department_id === auth()->user()->department_id))
                <a class="btn-secondary" href="{{ route('sales-quotations.edit', $salesQuotation) }}">Edit</a>
                <form method="POST" action="{{ route('sales-quotations.submit', $salesQuotation) }}">@csrf<button class="btn-primary" type="submit">Submit for Approval</button></form>
            @endif
            @if($canTrackOutcome && in_array($salesQuotation->status, [\App\Models\SalesQuotation::STATUS_APPROVED, \App\Models\SalesQuotation::STATUS_SENT], true))
                <form method="POST" action="{{ route('sales-quotations.mark-sent', $salesQuotation) }}">@csrf<button class="btn-secondary" type="submit">Mark Sent</button></form>
                <form method="POST" action="{{ route('sales-quotations.mark-accepted', $salesQuotation) }}">@csrf<button class="btn-primary" type="submit">Client Accepted</button></form>
                <form method="POST" action="{{ route('sales-quotations.mark-declined', $salesQuotation) }}">@csrf<button class="btn-secondary" type="submit">Client Declined</button></form>
                <form method="POST" action="{{ route('sales-quotations.mark-expired', $salesQuotation) }}">@csrf<button class="btn-secondary" type="submit">Expired</button></form>
            @endif
            @if($canCreateJobCard)
                <a class="btn-primary" href="{{ route('job-cards.create', ['sales_quotation_id' => $salesQuotation->id]) }}">Create Job Card</a>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
        <section class="panel">
            <h2 class="section-title">Quotation Lines</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Discount</th><th>VAT</th><th>Total</th></tr></thead>
                    <tbody>
                        @foreach($salesQuotation->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td>{{ number_format((float) $item->quantity, 2) }}</td>
                                <td>E{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td>E{{ number_format((float) $item->discount_amount, 2) }}</td>
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
                <h2 class="section-title">Totals</h2>
                <div class="finance-total-box mt-4 w-full">
                    <div><span>Subtotal</span><strong>E{{ number_format((float) $salesQuotation->subtotal, 2) }}</strong></div>
                    <div><span>VAT {{ $vatRate }}%</span><strong>E{{ number_format((float) $salesQuotation->vat_total, 2) }}</strong></div>
                    <div><span>Total</span><strong>E{{ number_format((float) $salesQuotation->total, 2) }}</strong></div>
                </div>
            </section>

            @if(auth()->user()->canApproveFinance() && $salesQuotation->status === \App\Models\SalesQuotation::STATUS_SUBMITTED)
                <section class="panel">
                    <h2 class="section-title">Director Approval</h2>
                    <form class="mt-4 space-y-3" method="POST" action="{{ route('sales-quotations.approve', $salesQuotation) }}">
                        @csrf
                        <textarea class="input min-h-20" name="approval_notes" placeholder="Optional approval notes"></textarea>
                        <button class="btn-primary w-full" type="submit">Approve</button>
                    </form>
                    <form class="mt-3 space-y-3" method="POST" action="{{ route('sales-quotations.reject', $salesQuotation) }}">
                        @csrf
                        <textarea class="input min-h-20" name="approval_notes" placeholder="Required rejection reason" required></textarea>
                        <button class="btn-danger w-full" type="submit">Reject</button>
                    </form>
                </section>
            @endif

            <section class="panel">
                <h2 class="section-title">Workflow</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="label">Created By</dt><dd>{{ $salesQuotation->creator?->name ?? 'Unknown' }}</dd></div>
                    <div><dt class="label">Department</dt><dd>{{ $salesQuotation->department?->name ?? 'Unassigned' }}</dd></div>
                    <div><dt class="label">Approved By</dt><dd>{{ $salesQuotation->approver?->name ?? 'Not approved' }}</dd></div>
                    <div><dt class="label">Approval Notes</dt><dd class="whitespace-pre-line">{{ $salesQuotation->approval_notes ?: 'None' }}</dd></div>
                    <div><dt class="label">Sent Externally</dt><dd>{{ $salesQuotation->sent_at?->toDayDateTimeString() ?? 'Not marked sent' }}</dd></div>
                    <div><dt class="label">Accepted</dt><dd>{{ $salesQuotation->accepted_at?->toDayDateTimeString() ?? 'Not accepted' }}</dd></div>
                    @if($salesQuotation->invoice)
                        <div><dt class="label">Invoice</dt><dd><a class="link" href="{{ route('invoices.show', $salesQuotation->invoice) }}">{{ $salesQuotation->invoice->invoice_number }}</a></dd></div>
                    @endif
                </dl>
            </section>

            <section class="panel">
                <h2 class="section-title">Job Cards</h2>
                <div class="mt-4 divide-y divide-neutral-100">
                    @forelse($salesQuotation->jobCards as $jobCard)
                        <div class="list-row">
                            <span><strong>{{ $jobCard->job_card_number }}</strong><small>{{ $jobCard->status }} | {{ $jobCard->due_date?->toFormattedDateString() ?? 'No due date' }}</small></span>
                            <em><a class="link" href="{{ route('job-cards.show', $jobCard) }}">Open</a></em>
                        </div>
                    @empty
                        <p class="empty">No job card has been created for this quotation yet.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
@endsection
