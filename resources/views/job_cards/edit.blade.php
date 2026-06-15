@extends('layouts.app')

@section('content')
    <div>
        <h1 class="page-title">Edit {{ $jobCard->job_card_number }}</h1>
        <p class="page-subtitle">{{ $jobCard->client->name }} | {{ $jobCard->status }}</p>
    </div>

    <form class="panel mt-6 space-y-5" method="POST" action="{{ route('job-cards.update', $jobCard) }}">
        @csrf
        @method('PUT')
        @include('job_cards.form')
        <div class="flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('job-cards.show', $jobCard) }}">Cancel</a>
            <button class="btn-primary" type="submit">Save Changes</button>
        </div>
    </form>
@endsection
