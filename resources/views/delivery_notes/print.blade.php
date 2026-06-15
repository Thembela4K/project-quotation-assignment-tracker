<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $deliveryNote->delivery_note_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 32px; }
        .top { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #087aa5; padding-bottom: 18px; }
        .logo { max-width: 170px; max-height: 80px; object-fit: contain; }
        h1 { margin: 0; font-size: 26px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border-bottom: 1px solid #d4d4d4; padding: 10px; text-align: left; vertical-align: top; }
        th { background: #f5f5f5; font-size: 12px; text-transform: uppercase; }
        .right { text-align: right; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 24px; }
        .box { border: 1px solid #d4d4d4; padding: 14px; min-height: 80px; }
        .label { display: block; color: #525252; font-size: 12px; text-transform: uppercase; margin-bottom: 8px; }
        .signature { height: 72px; border-bottom: 1px solid #777; margin-top: 32px; }
        @media print { button { display: none; } body { margin: 18mm; } }
    </style>
</head>
<body>
    <button onclick="window.print()">Print / Save PDF</button>
    <div class="top">
        <div>
            @if(file_exists(public_path('images/app-logo.png')))
                <img class="logo" src="{{ asset('images/app-logo.png') }}" alt="Company logo">
            @endif
            <h2>{{ config('company.email_signature.company', 'Your Company') }}</h2>
        </div>
        <div class="right">
            <h1>Delivery Note</h1>
            <p><strong>{{ $deliveryNote->delivery_note_number }}</strong></p>
            <p>Date: {{ $deliveryNote->delivery_date?->toFormattedDateString() ?? now()->toFormattedDateString() }}</p>
        </div>
    </div>

    <div class="grid">
        <div class="box">
            <span class="label">Client</span>
            <strong>{{ $deliveryNote->client->name }}</strong><br>
            {!! nl2br(e($deliveryNote->client->address)) !!}
        </div>
        <div class="box">
            <span class="label">Source</span>
            Job Card: {{ $deliveryNote->jobCard->job_card_number }}<br>
            Quotation: {{ $deliveryNote->salesQuotation->quotation_number }}<br>
            Department: {{ $deliveryNote->department?->name ?? 'Unassigned' }}
        </div>
    </div>

    <table>
        <tbody>
            <tr><th>Recipient</th><td>{{ $deliveryNote->recipient_name ?: '-' }}</td></tr>
            <tr><th>Phone</th><td>{{ $deliveryNote->recipient_phone ?: '-' }}</td></tr>
            <tr><th>Delivery Address</th><td>{!! nl2br(e($deliveryNote->delivery_address ?: '-')) !!}</td></tr>
            <tr><th>Items / Notes</th><td>{!! nl2br(e($deliveryNote->notes ?: '-')) !!}</td></tr>
        </tbody>
    </table>

    <div class="grid">
        <div>
            <div class="signature"></div>
            <span class="label">Issued By</span>
        </div>
        <div>
            <div class="signature"></div>
            <span class="label">Received By</span>
        </div>
    </div>
</body>
</html>
