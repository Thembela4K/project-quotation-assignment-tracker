@extends('emails.layout')

@section('title', 'Portal Invitation')
@section('preheader', 'You have been invited to create your Datamatics Eswatini portal account.')
@section('heading', 'Portal invitation')

@section('content')
    <p style="margin:0 0 14px; font-size:15px; line-height:23px;">
        Hello {{ $user->name }},
    </p>

    <p style="margin:0 0 14px; font-size:15px; line-height:23px;">
        You have been invited to join the Datamatics Eswatini Business Operations Portal.
        Use the secure link below to set your password and activate your account.
    </p>

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:22px 0;">
        <tr>
            <td style="background:#087aa5;">
                <a href="{{ $invitationUrl }}" style="display:inline-block; padding:12px 18px; color:#ffffff; text-decoration:none; font-weight:700;">
                    Accept invitation
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 8px; font-size:14px; line-height:22px; color:#374151;">
        Department: {{ $user->department?->name ?? 'Not assigned' }}<br>
        Role: {{ \App\Models\User::ROLES[$user->role] ?? $user->role }}<br>
        Link expires: {{ $invitation->expires_at->toDayDateTimeString() }}
    </p>

    <p style="margin:18px 0 0; font-size:13px; line-height:20px; color:#6b7280;">
        If the button does not open, copy this link into your browser:<br>
        <span style="word-break:break-all;">{{ $invitationUrl }}</span>
    </p>
@endsection
