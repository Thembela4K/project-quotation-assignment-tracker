@extends('layouts.app')

@section('content')
    <div>
        <h1 class="page-title">New Job Card</h1>
        <p class="page-subtitle">Create a department work record from an accepted quotation.</p>
    </div>

    <form class="panel mt-6 space-y-5" method="POST" action="{{ route('job-cards.store') }}">
        @csrf
        @include('job_cards.form')
        <div class="flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('job-cards.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">Create Job Card</button>
        </div>
    </form>
@endsection
