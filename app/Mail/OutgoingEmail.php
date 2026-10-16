<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OutgoingEmail extends Mailable
{
    public function __construct(
        public readonly string $emailSubject,
        public readonly string $emailBody,
        public readonly ?string $emailHtml = null,
        public readonly array $emailFiles = [],
    ) {}

    public function envelope(): Envelope { return new Envelope(subject: $this->emailSubject); }

    public function content(): Content
    {
        return $this->emailHtml !== null
            ? new Content(html: 'emails.outgoing-html', text: 'emails.outgoing', with: ['body' => $this->emailBody, 'html' => $this->emailHtml])
            : new Content(text: 'emails.outgoing', with: ['body' => $this->emailBody]);
    }

    public function attachments(): array
    {
        return array_map(fn (array $file) => Attachment::fromStorageDisk('local', $file['path'])
            ->as($file['filename'])->withMime($file['content_type']), $this->emailFiles);
    }
}
