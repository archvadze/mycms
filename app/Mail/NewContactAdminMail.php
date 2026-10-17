<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class NewContactAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public string $contactSubject,
        public string $messageText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Contact Message: ' . $this->contactSubject,
            replyTo: [new Address($this->email, $this->name)],
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: ['X-App-Notification-Type' => 'contact'],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin.new-contact',
        );
    }
}
