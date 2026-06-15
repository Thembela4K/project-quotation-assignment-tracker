@extends('layouts.app')

@section('content')
    <div>
        <h1 class="page-title">New Delivery Note</h1>
        <p class="page-subtitle">Prepare a reception-controlled handover note for a job card.</p>
    </div>

    <form class="panel mt-6 space-y-5" method="POST" action="{{ route('delivery-notes.store') }}">
        @csrf
        @include('delivery_notes.form')
        <div class="flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('delivery-notes.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">Create Delivery Note</button>
        </div>
    </form>
@endsection
