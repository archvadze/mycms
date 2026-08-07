<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailMessage extends Model
{
    protected $fillable = [
        'email_thread_id',
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
            'metadata' => 'array',
            'is_read' => 'boolean',
            'received_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class, 'email_thread_id');
    }
}
