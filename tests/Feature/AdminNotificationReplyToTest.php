<?php

namespace Tests\Feature;

use App\Mail\NewContactAdminMail;
use App\Mail\NewOrderAdminMail;
use App\Mail\NewSubscriptionRequestMail;
use App\Mail\NewUserAdminMail;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use Tests\TestCase;

class AdminNotificationReplyToTest extends TestCase
{
    public function test_contact_notification_uses_visitor_reply_to(): void
    {
        $mail = new NewContactAdminMail(
            'Test Visitor',
            'visitor@example.com',
            'Test subject',
            'Test message'
        );

        $this->assertSame(
            'visitor@example.com',
            $mail->envelope()->replyTo[0]->address
        );
    }

    public function test_registration_notification_uses_user_reply_to(): void
    {
        $user = new User([
            'name' => 'Test User',
            'email' => 'user@example.com',
        ]);

        $mail = new NewUserAdminMail($user);

        $this->assertSame(
            'user@example.com',
            $mail->envelope()->replyTo[0]->address
        );
    }

    public function test_order_notification_uses_client_reply_to(): void
    {
        $order = new Order([
            'client_name' => 'Test Client',
            'email' => 'client@example.com',
        ]);

        $mail = new NewOrderAdminMail($order);

        $this->assertSame(
            'client@example.com',
            $mail->envelope()->replyTo[0]->address
        );
    }

    public function test_subscription_notification_uses_user_reply_to(): void
    {
        $user = new User([
            'name' => 'Test Subscriber',
            'email' => 'subscriber@example.com',
        ]);

        $subscription = new Subscription();
        $subscription->setRelation('user', $user);

        $mail = new NewSubscriptionRequestMail($subscription);

        $this->assertSame(
            'subscriber@example.com',
            $mail->envelope()->replyTo[0]->address
        );
    }
}
