@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-xl">
        <section class="panel">
            <span class="label">Password Recovery</span>
            <h1 class="page-title mt-1">Request Reset Code</h1>
            <p class="page-subtitle">Enter the email address that was invited to the portal.</p>

            <form class="mt-6 space-y-4" method="POST" action="{{ route('password.email') }}">
                @csrf
                <label class="block">
                    <span class="label">Email Address</span>
                    <input class="input" type="email" name="email" value="{{ old('email') }}" required autofocus>
                </label>
                <button class="btn-primary w-full" type="submit">Send Reset Code</button>
                <a class="btn-secondary block text-center" href="{{ route('login') }}">Back to Sign In</a>
            </form>
        </section>
    </div>
@endsection
