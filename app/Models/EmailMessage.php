<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailMessage extends Model
{
    protected $fillable = [
        'email_thread_id',
        'status',
        'recipients',
        'cc_recipients',
        'bcc_recipients',
        'draft_saved_at',
        'failed_at',
        'sending_started_at',
        'send_attempts',
        'sending_token',
        'last_send_error',
        'direction',
        'source',
        'message_id',
        'in_reply_to',
        'from_name',
        'from_email',
        'to_email',
        'subject',
        'text_body',
        'html_body',
        'attachments',
        'metadata',
        'is_read',
        'received_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'recipients' => 'array',
            'cc_recipients' => 'array',
            'bcc_recipients' => 'array',
            'draft_saved_at' => 'datetime',
            'failed_at' => 'datetime',
            'sending_started_at' => 'datetime',
            'send_attempts' => 'integer',
            'metadata' => 'array',
            'is_read' => 'boolean',
            'received_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function notificationType(): string
    {
        $type = $this->metadata['notification_type'] ?? null;

        return is_string($type) && in_array(
            $type,
            ['contact', 'registration', 'order', 'subscription'],
            true
        ) ? $type : 'regular';
    }

    public function replyRecipient(): string
    {
        $replyTo = $this->metadata['reply_to'] ?? null;

        if (is_array($replyTo)) {
            $replyTo = $replyTo[0] ?? null;
        }

        if (is_string($replyTo)) {
            $replyTo = trim($replyTo);

            if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                return $replyTo;
            }
        }

        return $this->from_email;
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class, 'email_thread_id');
    }
}
