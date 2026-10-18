<?php
namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class NewOrderAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->order->email, $this->order->client_name)],
            subject: '🆕 New Order #' . $this->order->id . ' — ' . $this->order->domain,
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-App-Notification-Type' => 'order',
                'X-App-Actor-Name' => mb_substr(
                    trim(str_replace(["\r", "\n"], ' ', (string) $this->order->client_name)),
                    0,
                    120
                ),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin.new-order',
        );
    }
}
