@extends('layouts.app')

@section('content')
    <div class="login-shell">
        <section class="login-card">
            <div class="login-brand-panel">
                <div class="login-logo-frame">
                    @if(file_exists(public_path('images/app-logo.png')))
                        <img src="{{ asset('images/app-logo.png') }}" alt="Company logo">
                    @else
                        <span>{{ config('company.email_signature.company', 'Your Company') }}</span>
                    @endif
                </div>

                <div class="login-brand-copy">
                    <span>Internal Workspace</span>
                    <h1>Datamatics Eswatini</h1>
                </div>

                <div class="login-access-note">
                    <strong>Authorized Access</strong>
                    <span>Use your assigned staff account.</span>
                </div>
            </div>

            <div class="login-form-panel">
                <div class="login-mobile-brand">
                    @if(file_exists(public_path('images/app-logo.png')))
                        <img src="{{ asset('images/app-logo.png') }}" alt="Company logo">
                    @endif
                </div>

                <div class="login-form-heading">
                    <span>Secure Sign In</span>
                    <h2>Welcome back</h2>
                </div>

                <form method="POST" action="{{ route('login.store') }}" class="login-form">
                    @csrf
                    <label class="block">
                        <span class="label">Username or email</span>
                        <input class="input h-11" name="login" value="{{ old('login') }}" autocomplete="username" required autofocus>
                    </label>
                    <label class="block">
                        <span class="label">Password</span>
                        <span class="password-input-wrap">
                            <input class="input h-11 pr-12" type="password" name="password" autocomplete="current-password" required data-password-input>
                            <button class="password-toggle" type="button" aria-label="Show password" data-password-toggle>
                                <svg data-eye-open xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2.06 12.35a10.75 10.75 0 0 1 19.88 0 10.75 10.75 0 0 1-19.88 0"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="hidden" data-eye-closed xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m2 2 20 20"></path>
                                    <path d="M10.58 10.58A2 2 0 0 0 12 14a2 2 0 0 0 1.42-.58"></path>
                                    <path d="M9.88 4.24A10.56 10.56 0 0 1 12 4c5 0 9.27 3.11 11 8a11.66 11.66 0 0 1-2.18 3.32"></path>
                                    <path d="M6.61 6.61A11.8 11.8 0 0 0 1 12a11.64 11.64 0 0 0 15.39 6.39"></path>
                                </svg>
                            </button>
                        </span>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-neutral-600">
                        <input type="checkbox" name="remember" value="1" class="rounded border-neutral-300 text-[#087aa5] focus:ring-[#087aa5]">
                        Remember me
                    </label>
                    <button class="btn-primary h-11 w-full" type="submit">Sign in</button>
                    <a class="block text-center text-sm font-semibold text-[#087aa5]" href="{{ route('password.request') }}">Forgot password?</a>
                </form>
            </div>
        </section>
    </div>
@endsection
