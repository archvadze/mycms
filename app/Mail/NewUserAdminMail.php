<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class NewUserAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->user->email, $this->user->name)],
            subject: 'New User Registration — ' . $this->user->name,
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-App-Notification-Type' => 'registration',
                'X-App-Actor-Name' => mb_substr(
                    trim(str_replace(["\r", "\n"], ' ', (string) $this->user->name)),
                    0,
                    120
                ),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.users.new-user-admin',
        );
    }
}
