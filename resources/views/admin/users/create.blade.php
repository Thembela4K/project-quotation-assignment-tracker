@extends('layouts.app')

@section('content')
    <h1 class="page-title">Invite User</h1>
    <p class="page-subtitle">Assign role and department, then send a secure account setup link.</p>
    <form class="panel mt-6" method="POST" action="{{ route('users.store') }}">
        @csrf
        @include('admin.users.form')
        <div class="mt-6 flex gap-2">
            <button class="btn-primary" type="submit">Send Invitation</button>
            <a class="btn-secondary" href="{{ route('users.index') }}">Cancel</a>
        </div>
    </form>
@endsection
