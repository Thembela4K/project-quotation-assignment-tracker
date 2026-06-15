Portal invitation

Hello {{ $user->name }},

You have been invited to join the Datamatics Eswatini Business Operations Portal.

Department: {{ $user->department?->name ?? 'Not assigned' }}
Role: {{ \App\Models\User::ROLES[$user->role] ?? $user->role }}
Link expires: {{ $invitation->expires_at->toDayDateTimeString() }}

Accept your invitation:
{{ $invitationUrl }}
