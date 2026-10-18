<?php

namespace Tests\Feature;

use App\Models\EmailMessage;
use Tests\TestCase;

class EmailLegacyDisplayTest extends TestCase
{
    public function test_missing_notification_type_displays_legacy(): void
    {
        $message = new EmailMessage(['metadata' => []]);

        $this->assertSame('Legacy', $message->displayNotificationType());
    }

    public function test_known_notification_types_display_correctly(): void
    {
        foreach (['contact', 'order', 'subscription', 'registration'] as $type) {
            $message = new EmailMessage([
                'metadata' => ['notification_type' => $type],
            ]);

            $this->assertSame(
                ucfirst($type),
                $message->displayNotificationType()
            );
        }
    }

    public function test_existing_classification_logic_is_unchanged(): void
    {
        $message = new EmailMessage(['metadata' => []]);

        $this->assertSame('regular', $message->notificationType());
        $this->assertSame('Legacy', $message->displayNotificationType());
    }

    public function test_unrecognized_non_null_type_displays_regular(): void
    {
        $message = new EmailMessage([
            'metadata' => ['notification_type' => 'unknown'],
        ]);

        $this->assertSame('Regular', $message->displayNotificationType());
    }
}
