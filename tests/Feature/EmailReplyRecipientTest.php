<?php

namespace Tests\Feature;

use App\Models\EmailMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailReplyRecipientTest extends TestCase
{
    public static function recipients(): array
    {
        return [
            'contact form' => [
                ['reply_to' => ['visitor@example.com']],
                'visitor@example.com',
            ],
            'ordinary email' => [
                [],
                'admin@example.com',
            ],
            'invalid reply to' => [
                ['reply_to' => ['invalid-address']],
                'admin@example.com',
            ],
            'string reply to' => [
                ['reply_to' => 'visitor@example.com'],
                'visitor@example.com',
            ],
            'empty reply to' => [
                ['reply_to' => []],
                'admin@example.com',
            ],
        ];
    }

    #[DataProvider('recipients')]
    public function test_reply_recipient(
        array $metadata,
        string $expected
    ): void {
        $message = new EmailMessage([
            'from_email' => 'admin@example.com',
            'metadata' => $metadata,
        ]);

        $this->assertSame(
            $expected,
            $message->replyRecipient()
        );
    }
}
