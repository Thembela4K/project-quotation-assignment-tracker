@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $deliveryNote->delivery_note_number }}</h1>
            <p class="page-subtitle">{{ $deliveryNote->client->name }} | {{ $deliveryNote->status }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('delivery-notes.print', $deliveryNote) }}" target="_blank">Print</a>
            <a class="btn-secondary" href="{{ route('job-cards.show', $deliveryNote->jobCard) }}">Job Card</a>
            @if(auth()->user()->canManageFinance())
                @if(in_array($deliveryNote->status, [\App\Models\DeliveryNote::STATUS_DRAFT, \App\Models\DeliveryNote::STATUS_ISSUED], true))
                    <a class="btn-secondary" href="{{ route('delivery-notes.edit', $deliveryNote) }}">Edit</a>
                @endif
                @if($deliveryNote->status === \App\Models\DeliveryNote::STATUS_DRAFT)
                    <form method="POST" action="{{ route('delivery-notes.issue', $deliveryNote) }}">@csrf<button class="btn-primary" type="submit">Issue</button></form>
                @endif
                @if(in_array($deliveryNote->status, [\App\Models\DeliveryNote::STATUS_DRAFT, \App\Models\DeliveryNote::STATUS_ISSUED], true))
                    <form method="POST" action="{{ route('delivery-notes.delivered', $deliveryNote) }}">@csrf<button class="btn-secondary" type="submit">Mark Delivered</button></form>
                @endif
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
        <section class="panel">
            <h2 class="section-title">Delivery Details</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <span class="label">Client</span>
                    <p>{{ $deliveryNote->client->name }}</p>
                </div>
                <div>
                    <span class="label">Department</span>
                    <p>{{ $deliveryNote->department?->name ?? 'Unassigned' }}</p>
                </div>
                <div>
                    <span class="label">Recipient</span>
                    <p>{{ $deliveryNote->recipient_name ?: '-' }}</p>
                </div>
                <div>
                    <span class="label">Phone</span>
                    <p>{{ $deliveryNote->recipient_phone ?: '-' }}</p>
                </div>
                <div>
                    <span class="label">Delivery Date</span>
                    <p>{{ $deliveryNote->delivery_date?->toFormattedDateString() ?? '-' }}</p>
                </div>
                <div>
                    <span class="label">Issued By</span>
                    <p>{{ $deliveryNote->issuer?->name ?? 'Not issued' }}</p>
                </div>
            </div>

            <div class="mt-6">
                <span class="label">Delivery Address</span>
                <p class="mt-2 whitespace-pre-line text-sm text-neutral-700">{{ $deliveryNote->delivery_address ?: '-' }}</p>
            </div>

            <div class="mt-6">
                <span class="label">Items / Notes</span>
                <p class="mt-2 whitespace-pre-line text-sm text-neutral-700">{{ $deliveryNote->notes ?: '-' }}</p>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="panel">
                <h2 class="section-title">Source</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="label">Job Card</dt><dd><a class="link" href="{{ route('job-cards.show', $deliveryNote->jobCard) }}">{{ $deliveryNote->jobCard->job_card_number }}</a></dd></div>
                    <div><dt class="label">Quotation</dt><dd><a class="link" href="{{ route('sales-quotations.show', $deliveryNote->salesQuotation) }}">{{ $deliveryNote->salesQuotation->quotation_number }}</a></dd></div>
                    <div><dt class="label">Created By</dt><dd>{{ $deliveryNote->creator?->name ?? 'Unknown' }}</dd></div>
                    <div><dt class="label">Created</dt><dd>{{ $deliveryNote->created_at->toDayDateTimeString() }}</dd></div>
                </dl>
            </section>
        </aside>
    </div>
@endsection
