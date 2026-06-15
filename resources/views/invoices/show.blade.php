@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $invoice->invoice_number }}</h1>
            <p class="page-subtitle">{{ $invoice->client->name }} | {{ $invoice->status }} | Balance E{{ number_format((float) $invoice->balance_due, 2) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('invoices.print', $invoice) }}" target="_blank">Print</a>
            <a class="btn-secondary" href="{{ route('invoices.pdf', $invoice) }}">PDF</a>
            @if(auth()->user()->canManageFinance())
                @if(in_array($invoice->status, [\App\Models\Invoice::STATUS_DRAFT, \App\Models\Invoice::STATUS_ISSUED], true))
                    <a class="btn-secondary" href="{{ route('invoices.edit', $invoice) }}">Edit</a>
                    <form method="POST" action="{{ route('invoices.issue', $invoice) }}">@csrf<button class="btn-primary" type="submit">Issue</button></form>
                @endif
                <form method="POST" action="{{ route('invoices.mark-sent', $invoice) }}">@csrf<button class="btn-secondary" type="submit">Mark Sent</button></form>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_380px]">
        <section class="panel">
            <h2 class="section-title">Invoice Lines</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Discount</th><th>VAT</th><th>Total</th></tr></thead>
                    <tbody>
                        @foreach($invoice->items as $item)
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
                    <div><span>Subtotal</span><strong>E{{ number_format((float) $invoice->subtotal, 2) }}</strong></div>
                    <div><span>VAT {{ $vatRate }}%</span><strong>E{{ number_format((float) $invoice->vat_total, 2) }}</strong></div>
                    <div><span>Total</span><strong>E{{ number_format((float) $invoice->total, 2) }}</strong></div>
                    <div><span>Paid</span><strong>E{{ number_format((float) $invoice->amount_paid, 2) }}</strong></div>
                    <div><span>Balance</span><strong>E{{ number_format((float) $invoice->balance_due, 2) }}</strong></div>
                </div>
            </section>

            <section class="panel">
                <h2 class="section-title">Source</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    @if($invoice->jobCard)
                        <div><dt class="label">Job Card</dt><dd><a class="link" href="{{ route('job-cards.show', $invoice->jobCard) }}">{{ $invoice->jobCard->job_card_number }}</a></dd></div>
                    @else
                        <div><dt class="label">Job Card</dt><dd>Not linked</dd></div>
                    @endif
                    @if($invoice->salesQuotation)
                        <div><dt class="label">Quotation</dt><dd><a class="link" href="{{ route('sales-quotations.show', $invoice->salesQuotation) }}">{{ $invoice->salesQuotation->quotation_number }}</a></dd></div>
                    @endif
                    <div><dt class="label">Department</dt><dd>{{ $invoice->department?->name ?? 'Unassigned' }}</dd></div>
                </dl>
            </section>

            @if(auth()->user()->canManageFinance() && ! in_array($invoice->status, [\App\Models\Invoice::STATUS_DRAFT, \App\Models\Invoice::STATUS_PAID, \App\Models\Invoice::STATUS_CANCELLED], true))
                <section class="panel">
                    <h2 class="section-title">Record Payment</h2>
                    <form class="mt-4 space-y-3" method="POST" action="{{ route('payments.store', $invoice) }}">
                        @csrf
                        <label><span class="label">Date</span><input class="input" type="date" name="payment_date" value="{{ now()->format('Y-m-d') }}" required></label>
                        <label><span class="label">Amount</span><input class="input" type="number" min="0.01" step="0.01" name="amount" value="{{ $invoice->balance_due }}" required></label>
                        <label><span class="label">Method</span><select class="input" name="method">@foreach($paymentMethods as $method)<option>{{ $method }}</option>@endforeach</select></label>
                        <label><span class="label">Reference</span><input class="input" name="reference"></label>
                        <label><span class="label">Notes</span><textarea class="input min-h-20" name="notes"></textarea></label>
                        <button class="btn-primary w-full" type="submit">Record Payment</button>
                    </form>
                </section>
            @endif

            <section class="panel">
                <h2 class="section-title">Payments</h2>
                <div class="mt-4 divide-y divide-neutral-100">
                    @forelse($invoice->payments as $payment)
                        <div class="list-row">
                            <span><strong>{{ $payment->payment_number }}</strong><small>{{ $payment->method }} | {{ $payment->payment_date->toFormattedDateString() }}</small></span>
                            <em><a class="link" href="{{ route('payments.pdf', $payment) }}">PDF</a> | E{{ number_format((float) $payment->amount, 2) }}</em>
                        </div>
                    @empty
                        <p class="empty">No payments recorded.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
@endsection
