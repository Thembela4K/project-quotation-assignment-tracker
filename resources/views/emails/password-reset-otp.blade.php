@extends('emails.layout')

@section('title', 'Password Reset Code')
@section('preheader', 'Use this code to reset your Datamatics Eswatini portal password.')
@section('heading', 'Password reset code')

@section('content')
    <p style="margin:0 0 14px; font-size:15px; line-height:23px;">
        Hello {{ $user->name }},
    </p>

    <p style="margin:0 0 14px; font-size:15px; line-height:23px;">
        Use this one-time code to reset your portal password. It expires in {{ $expiresInMinutes }} minutes.
    </p>

    <div style="display:inline-block; margin:16px 0 20px; padding:14px 22px; border:1px solid #d9e2e8; background:#f8fafc; font-size:28px; letter-spacing:6px; font-weight:700; color:#083c5a;">
        {{ $otp }}
    </div>

    <p style="margin:0; font-size:13px; line-height:20px; color:#6b7280;">
        If you did not request this code, you can ignore this email. Your current password will remain unchanged.
    </p>
@endsection
