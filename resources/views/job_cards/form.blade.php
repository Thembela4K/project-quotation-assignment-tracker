@php
    $startValue = $jobCard->start_date instanceof \Carbon\CarbonInterface ? $jobCard->start_date->format('Y-m-d') : $jobCard->start_date;
    $dueValue = $jobCard->due_date instanceof \Carbon\CarbonInterface ? $jobCard->due_date->format('Y-m-d') : $jobCard->due_date;
@endphp

@if($quotation)
    <input type="hidden" name="sales_quotation_id" value="{{ $quotation->id }}">
    <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950">
        <strong>{{ $quotation->quotation_number }}</strong> | {{ $quotation->client->name }} | {{ $quotation->department?->name ?? 'Unassigned' }}
    </div>
@else
    <label>
        <span class="label">Accepted Quotation</span>
        <select class="input" name="sales_quotation_id" required>
            <option value="">Select accepted quotation</option>
            @foreach($quotations ?? [] as $availableQuotation)
                <option value="{{ $availableQuotation->id }}" @selected((string) old('sales_quotation_id', $jobCard->sales_quotation_id) === (string) $availableQuotation->id)>
                    {{ $availableQuotation->quotation_number }} | {{ $availableQuotation->client->name }} | {{ $availableQuotation->title }}
                </option>
            @endforeach
        </select>
    </label>
@endif

<div class="grid gap-4 md:grid-cols-2">
    <label>
        <span class="label">Job Card Number</span>
        <input class="input" name="job_card_number" value="{{ old('job_card_number', $jobCard->job_card_number) }}" @readonly($jobCard->exists)>
    </label>
    <label>
        <span class="label">Assigned Staff</span>
        <select class="input" name="assigned_to">
            <option value="">Department team</option>
            @foreach($staff as $staffUser)
                <option value="{{ $staffUser->id }}" @selected((string) old('assigned_to', $jobCard->assigned_to) === (string) $staffUser->id)>{{ $staffUser->name }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="label">Start Date</span>
        <input class="input" type="date" name="start_date" value="{{ old('start_date', $startValue) }}">
    </label>
    <label>
        <span class="label">Due Date</span>
        <input class="input" type="date" name="due_date" value="{{ old('due_date', $dueValue) }}">
    </label>
</div>

<label>
    <span class="label">Work Title</span>
    <input class="input" name="title" value="{{ old('title', $jobCard->title) }}" required>
</label>

<label>
    <span class="label">Scope / Work Done</span>
    <textarea class="input min-h-28" name="scope" placeholder="Describe the work the department will do or has completed.">{{ old('scope', $jobCard->scope) }}</textarea>
</label>

<label>
    <span class="label">Internal Notes</span>
    <textarea class="input min-h-24" name="notes" placeholder="Notes for reception or directors.">{{ old('notes', $jobCard->notes) }}</textarea>
</label>

<label class="flex items-center gap-3 text-sm">
    <input type="hidden" name="delivery_required" value="0">
    <input class="h-4 w-4" type="checkbox" name="delivery_required" value="1" @checked(old('delivery_required', $jobCard->delivery_required))>
    <span>Delivery note required for consumables, devices, or physical handover.</span>
</label>
