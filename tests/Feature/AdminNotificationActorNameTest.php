<?php

namespace Tests\Feature;

use App\Listeners\HandleReceivedEmail;
use App\Mail\NewContactAdminMail;
use App\Mail\NewOrderAdminMail;
use App\Mail\NewSubscriptionRequestMail;
use App\Mail\NewUserAdminMail;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use ReflectionClass;
use Tests\TestCase;

class AdminNotificationActorNameTest extends TestCase
{
    public function test_all_admin_notifications_include_actor_name(): void
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

        $cases = [
            [
                new NewContactAdminMail(
                    'Contact Visitor',
                    'visitor@example.com',
                    'Question',
                    'Hello'
                ),
                'Contact Visitor',
            ],
            [new NewUserAdminMail($user), 'Test User'],
            [new NewOrderAdminMail($order), 'Test Client'],
            [new NewSubscriptionRequestMail($subscription), 'Test User'],
        ];

        foreach ($cases as [$mail, $expected]) {
            $this->assertSame(
                $expected,
                $mail->headers()->text['X-App-Actor-Name'] ?? null
            );
        }
    }

    public function test_actor_name_parser_handles_valid_and_invalid_values(): void
    {
        $reflection = new ReflectionClass(HandleReceivedEmail::class);
        $handler = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('notificationActorName');

        $this->assertSame(
            'Contact Visitor',
            $method->invoke($handler, [
                'X-App-Actor-Name' => 'Contact Visitor',
            ])
        );

        $this->assertNull(
            $method->invoke($handler, [
                'X-App-Actor-Name' => '',
            ])
        );

        $this->assertNull(
            $method->invoke($handler, [
                'X-App-Actor-Name' => ['Invalid'],
            ])
        );

        $this->assertNull($method->invoke($handler, []));
    }

    public function test_contact_name_is_limited_and_cleaned(): void
    {
        $mail = new NewContactAdminMail(
            str_repeat('A', 150),
            'visitor@example.com',
            'Question',
            'Hello'
        );

        $name = $mail->headers()->text['X-App-Actor-Name'];

        $this->assertSame(120, mb_strlen($name));
    }
}
