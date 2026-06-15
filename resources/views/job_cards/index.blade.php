@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Job Cards</h1>
            <p class="page-subtitle">Department work records created from accepted client quotations before reception invoices.</p>
        </div>
        @if(auth()->user()->canDraftFinance())
            <a class="btn-primary" href="{{ route('job-cards.create') }}">New Job Card</a>
        @endif
    </div>

    <form class="panel mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(170px,220px)_minmax(190px,240px)_auto]" method="GET">
        <input class="input" name="search" value="{{ request('search') }}" placeholder="Search job card, quotation, client, or title">
        <select class="input" name="status">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @if(auth()->user()->canViewReports())
            <select class="input" name="department_id">
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        @endif
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    <section class="panel mt-6 overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Job Card</th><th>Quotation</th><th>Client</th><th>Department</th><th>Status</th><th>Due</th><th>Invoice</th><th></th></tr></thead>
                <tbody>
                    @forelse($jobCards as $jobCard)
                        <tr>
                            <td><strong>{{ $jobCard->job_card_number }}</strong><br><span class="text-xs text-neutral-500">{{ $jobCard->title }}</span></td>
                            <td><a class="link" href="{{ route('sales-quotations.show', $jobCard->salesQuotation) }}">{{ $jobCard->salesQuotation->quotation_number }}</a></td>
                            <td>{{ $jobCard->client->name }}</td>
                            <td>{{ $jobCard->department?->name ?? 'Unassigned' }}</td>
                            <td>{{ $jobCard->status }}</td>
                            <td>{{ $jobCard->due_date?->toFormattedDateString() ?? '-' }}</td>
                            <td>
                                @if($jobCard->invoice)
                                    <a class="link" href="{{ route('invoices.show', $jobCard->invoice) }}">{{ $jobCard->invoice->invoice_number }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-right"><a class="link" href="{{ route('job-cards.show', $jobCard) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><p class="empty">No job cards yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $jobCards->links() }}</div>
    </section>
@endsection
