@extends('layouts.app')

@section('content')
    <div>
        <h1 class="page-title">Edit {{ $deliveryNote->delivery_note_number }}</h1>
        <p class="page-subtitle">{{ $deliveryNote->client->name }} | {{ $deliveryNote->status }}</p>
    </div>

    <form class="panel mt-6 space-y-5" method="POST" action="{{ route('delivery-notes.update', $deliveryNote) }}">
        @csrf
        @method('PUT')
        @include('delivery_notes.form')
        <div class="flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('delivery-notes.show', $deliveryNote) }}">Cancel</a>
            <button class="btn-primary" type="submit">Save Changes</button>
        </div>
    </form>
@endsection
