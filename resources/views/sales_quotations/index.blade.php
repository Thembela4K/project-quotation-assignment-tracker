@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Sales Quotations</h1>
            <p class="page-subtitle">Department quotations with 15% VAT, director approval, PDF download, and manual client outcome tracking.</p>
        </div>
        @if(auth()->user()->canDraftFinance())
            <a class="btn-primary" href="{{ route('sales-quotations.create') }}">New Sales Quotation</a>
        @endif
    </div>

    <form class="panel mt-6 grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(170px,220px)_auto]" method="GET">
        <input class="input" name="search" value="{{ request('search') }}" placeholder="Search quotation, client, or title">
        <select class="input" name="status">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    <section class="panel mt-6 overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Quotation</th><th>Client</th><th>Department</th><th>Status</th><th>Total</th><th>Valid Until</th><th></th></tr></thead>
                <tbody>
                    @forelse($salesQuotations as $quotation)
                        <tr>
                            <td><strong>{{ $quotation->quotation_number }}</strong><br><span class="text-xs text-neutral-500">{{ $quotation->title }}</span></td>
                            <td>{{ $quotation->client->name }}</td>
                            <td>{{ $quotation->department?->name ?? 'Unassigned' }}</td>
                            <td>{{ $quotation->status }}</td>
                            <td>E{{ number_format((float) $quotation->total, 2) }}</td>
                            <td>{{ $quotation->valid_until->toFormattedDateString() }}</td>
                            <td class="text-right"><a class="link" href="{{ route('sales-quotations.show', $quotation) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><p class="empty">No sales quotations yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $salesQuotations->links() }}</div>
    </section>
@endsection
