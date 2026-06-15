@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-2xl">
        <section class="panel">
            <div>
                <span class="label">Account Setup</span>
                <h1 class="page-title mt-1">Accept Invitation</h1>
                <p class="page-subtitle">
                    {{ $user->email }} | {{ $user->department?->name ?? 'No department' }} | {{ \App\Models\User::ROLES[$user->role] ?? $user->role }}
                </p>
            </div>

            <form class="mt-6 space-y-4" method="POST" action="{{ route('invitations.accept.store', $token) }}">
                @csrf
                <label class="block">
                    <span class="label">Full Name</span>
                    <input class="input" name="name" value="{{ old('name', $user->name) }}" required autofocus>
                </label>
                <label class="block">
                    <span class="label">Username</span>
                    <input class="input" name="username" value="{{ old('username', $user->username) }}" placeholder="name.surname">
                </label>
                <label class="block">
                    <span class="label">Password</span>
                    <span class="password-input-wrap">
                        <input class="input pr-12" type="password" name="password" autocomplete="new-password" required data-password-input>
                        <button class="password-toggle" type="button" aria-label="Show password" data-password-toggle>
                            <svg data-eye-open xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.06 12.35a10.75 10.75 0 0 1 19.88 0 10.75 10.75 0 0 1-19.88 0"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="hidden" data-eye-closed xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2 2 20 20"></path><path d="M10.58 10.58A2 2 0 0 0 12 14a2 2 0 0 0 1.42-.58"></path><path d="M9.88 4.24A10.56 10.56 0 0 1 12 4c5 0 9.27 3.11 11 8a11.66 11.66 0 0 1-2.18 3.32"></path><path d="M6.61 6.61A11.8 11.8 0 0 0 1 12a11.64 11.64 0 0 0 15.39 6.39"></path></svg>
                        </button>
                    </span>
                </label>
                <label class="block">
                    <span class="label">Confirm Password</span>
                    <input class="input" type="password" name="password_confirmation" autocomplete="new-password" required>
                </label>
                <button class="btn-primary w-full" type="submit">Activate Account</button>
            </form>
        </section>
    </div>
@endsection
