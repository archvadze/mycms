<?php
namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class NewSubscriptionRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->subscription->user->email, $this->subscription->user->name)],
            subject: 'New Subscription Request — ' . $this->subscription->user->name,
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: ['X-App-Notification-Type' => 'subscription'],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscription.new-request',
        );
    }
}
