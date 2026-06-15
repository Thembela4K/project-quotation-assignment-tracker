@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Invoices</h1>
            <p class="page-subtitle">Reception-issued invoices created from completed department job cards.</p>
        </div>
        @if(auth()->user()->canManageFinance())
            <a class="btn-primary" href="{{ route('job-cards.index', ['status' => \App\Models\JobCard::STATUS_READY_FOR_INVOICE]) }}">Ready Job Cards</a>
        @endif
    </div>

    <form class="panel mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(170px,220px)_minmax(190px,240px)_auto]" method="GET">
        <input class="input" name="search" value="{{ request('search') }}" placeholder="Search invoice or client">
        <select class="input" name="status">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <select class="input" name="payment_state">
            <option value="">Any payment state</option>
            <option value="unpaid" @selected(request('payment_state') === 'unpaid')>Unpaid / outstanding</option>
        </select>
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    <section class="panel mt-6 overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Invoice</th><th>Source</th><th>Client</th><th>Status</th><th>Total</th><th>Paid</th><th>Balance</th><th>Due</th><th></th></tr></thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td><strong>{{ $invoice->invoice_number }}</strong><br><span class="text-xs text-neutral-500">{{ $invoice->department?->name ?? 'Unassigned' }}</span></td>
                            <td>
                                @if($invoice->jobCard)
                                    <a class="link" href="{{ route('job-cards.show', $invoice->jobCard) }}">{{ $invoice->jobCard->job_card_number }}</a>
                                @elseif($invoice->salesQuotation)
                                    <a class="link" href="{{ route('sales-quotations.show', $invoice->salesQuotation) }}">{{ $invoice->salesQuotation->quotation_number }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $invoice->client->name }}</td>
                            <td>{{ $invoice->status }}</td>
                            <td>E{{ number_format((float) $invoice->total, 2) }}</td>
                            <td>E{{ number_format((float) $invoice->amount_paid, 2) }}</td>
                            <td>E{{ number_format((float) $invoice->balance_due, 2) }}</td>
                            <td>{{ $invoice->due_date->toFormattedDateString() }}</td>
                            <td class="text-right"><a class="link" href="{{ route('invoices.show', $invoice) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><p class="empty">No invoices yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $invoices->links() }}</div>
    </section>
@endsection
