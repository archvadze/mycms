<?php

namespace Tests\Feature;

use App\Listeners\HandleReceivedEmail;
use App\Mail\NewContactAdminMail;
use App\Mail\NewOrderAdminMail;
use App\Mail\NewSubscriptionRequestMail;
use App\Mail\NewUserAdminMail;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use ReflectionClass;
use Tests\TestCase;

class EmailNotificationTypeTest extends TestCase
{
    public function test_mailable_headers_identify_all_four_types(): void
    {
        $user = new User([
            'name' => 'Test User',
            'email' => 'user@example.com',
        ]);

        $order = new Order([
            'client_name' => 'Test Client',
            'email' => 'client@example.com',
        ]);

        $subscription = new Subscription();
        $subscription->setRelation('user', $user);

        $mailables = [
            'contact' => new NewContactAdminMail(
                'Visitor', 'visitor@example.com', 'Hello', 'Message'
            ),
            'registration' => new NewUserAdminMail($user),
            'order' => new NewOrderAdminMail($order),
            'subscription' => new NewSubscriptionRequestMail($subscription),
        ];

        foreach ($mailables as $type => $mail) {
            $this->assertSame(
                $type,
                $mail->headers()->text['X-App-Notification-Type'] ?? null
            );
        }
    }

    public function test_inbound_header_parser_accepts_only_known_types(): void
    {
        $reflection = new ReflectionClass(HandleReceivedEmail::class);
        $handler = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('notificationType');

        $this->assertSame(
            'contact',
            $method->invoke($handler, [
                'X-App-Notification-Type' => 'contact',
            ])
        );

        $this->assertSame(
            'order',
            $method->invoke($handler, [
                'x-app-notification-type' => 'ORDER',
            ])
        );

        $this->assertNull(
            $method->invoke($handler, [
                'X-App-Notification-Type' => 'unknown',
            ])
        );

        $this->assertNull($method->invoke($handler, []));
    }

    public function test_message_defaults_to_regular_email(): void
    {
        $message = new EmailMessage(['metadata' => []]);

        $this->assertSame('regular', $message->notificationType());

        $message->metadata = ['notification_type' => 'registration'];

        $this->assertSame('registration', $message->notificationType());

        $message->metadata = ['notification_type' => 'unexpected'];

        $this->assertSame('regular', $message->notificationType());
    }
}
