@php
    $deliveryDate = $deliveryNote->delivery_date instanceof \Carbon\CarbonInterface ? $deliveryNote->delivery_date->format('Y-m-d') : $deliveryNote->delivery_date;
@endphp

@if($jobCard)
    <input type="hidden" name="job_card_id" value="{{ $jobCard->id }}">
    <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950">
        <strong>{{ $jobCard->job_card_number }}</strong> | {{ $jobCard->client->name }} | {{ $jobCard->salesQuotation->quotation_number }}
    </div>
@else
    <label>
        <span class="label">Job Card</span>
        <select class="input" name="job_card_id" required>
            <option value="">Select job card</option>
            @foreach($jobCards as $availableJobCard)
                <option value="{{ $availableJobCard->id }}" @selected((string) old('job_card_id', $deliveryNote->job_card_id) === (string) $availableJobCard->id)>
                    {{ $availableJobCard->job_card_number }} | {{ $availableJobCard->client->name }} | {{ $availableJobCard->title }}
                </option>
            @endforeach
        </select>
    </label>
@endif

<div class="grid gap-4 md:grid-cols-2">
    <label>
        <span class="label">Delivery Note Number</span>
        <input class="input" name="delivery_note_number" value="{{ old('delivery_note_number', $deliveryNote->delivery_note_number) }}" @readonly($deliveryNote->exists)>
    </label>
    <label>
        <span class="label">Delivery Date</span>
        <input class="input" type="date" name="delivery_date" value="{{ old('delivery_date', $deliveryDate) }}">
    </label>
    <label>
        <span class="label">Recipient Name</span>
        <input class="input" name="recipient_name" value="{{ old('recipient_name', $deliveryNote->recipient_name) }}">
    </label>
    <label>
        <span class="label">Recipient Phone</span>
        <input class="input" name="recipient_phone" value="{{ old('recipient_phone', $deliveryNote->recipient_phone) }}">
    </label>
</div>

<label>
    <span class="label">Delivery Address</span>
    <textarea class="input min-h-24" name="delivery_address">{{ old('delivery_address', $deliveryNote->delivery_address) }}</textarea>
</label>

<label>
    <span class="label">Items / Notes</span>
    <textarea class="input min-h-28" name="notes" placeholder="List delivered equipment, consumables, serial numbers, or handover notes.">{{ old('notes', $deliveryNote->notes) }}</textarea>
</label>
