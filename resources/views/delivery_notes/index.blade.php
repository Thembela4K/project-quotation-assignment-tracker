@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Delivery Notes</h1>
            <p class="page-subtitle">Reception-issued handover records for devices, consumables, and physical deliveries.</p>
        </div>
        @if(auth()->user()->canManageFinance())
            <a class="btn-primary" href="{{ route('delivery-notes.create') }}">New Delivery Note</a>
        @endif
    </div>

    <form class="panel mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(170px,220px)_minmax(190px,240px)_auto]" method="GET">
        <input class="input" name="search" value="{{ request('search') }}" placeholder="Search delivery note, job card, quotation, or client">
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
                <thead><tr><th>Delivery Note</th><th>Job Card</th><th>Client</th><th>Department</th><th>Status</th><th>Delivery Date</th><th></th></tr></thead>
                <tbody>
                    @forelse($deliveryNotes as $deliveryNote)
                        <tr>
                            <td><strong>{{ $deliveryNote->delivery_note_number }}</strong><br><span class="text-xs text-neutral-500">{{ $deliveryNote->recipient_name ?: 'No recipient captured' }}</span></td>
                            <td><a class="link" href="{{ route('job-cards.show', $deliveryNote->jobCard) }}">{{ $deliveryNote->jobCard->job_card_number }}</a></td>
                            <td>{{ $deliveryNote->client->name }}</td>
                            <td>{{ $deliveryNote->department?->name ?? 'Unassigned' }}</td>
                            <td>{{ $deliveryNote->status }}</td>
                            <td>{{ $deliveryNote->delivery_date?->toFormattedDateString() ?? '-' }}</td>
                            <td class="text-right"><a class="link" href="{{ route('delivery-notes.show', $deliveryNote) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><p class="empty">No delivery notes yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $deliveryNotes->links() }}</div>
    </section>
@endsection
