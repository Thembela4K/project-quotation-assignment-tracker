<?php

namespace App\Mail;

use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public UserInvitation $invitation,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to the Datamatics Eswatini portal',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-invitation',
            text: 'emails.text.user-invitation',
            with: [
                'invitationUrl' => $this->invitationUrl(),
            ],
        );
    }

    public function invitationUrl(): string
    {
        return route('invitations.accept', $this->token);
    }
}
