<?php

namespace Tests\Feature;

use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\Order;
use App\Models\User;
use App\Services\AdminDashboardOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Support', 'guard_name' => 'web']);
    }

    public function test_email_metrics_count_open_and_unread_conversations(): void
    {
        $openUnread = EmailThread::create([
            'subject' => 'Open unread',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $openRead = EmailThread::create([
            'subject' => 'Open read',
            'status' => 'open',
            'last_message_at' => now()->subMinute(),
        ]);

        $closedUnread = EmailThread::create([
            'subject' => 'Closed unread',
            'status' => 'closed',
            'last_message_at' => now()->subMinutes(2),
        ]);

        $this->createInboundMessage($openUnread, '<open-unread@example.com>', false);
        $this->createInboundMessage($openRead, '<open-read@example.com>', true);
        $this->createInboundMessage($closedUnread, '<closed-unread@example.com>', false);

        $metrics = app(AdminDashboardOverview::class)->emailMetrics();

        $this->assertSame(2, $metrics['open_conversations']);
        $this->assertSame(2, $metrics['unread_conversations']);
    }

    public function test_recent_conversations_use_latest_inbound_message_per_thread(): void
    {
        $thread = EmailThread::create([
            'subject' => 'Threaded',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $older = $this->createInboundMessage(
            $thread,
            '<older@example.com>',
            true,
            now()->subMinutes(5)
        );

        $latest = $this->createInboundMessage(
            $thread,
            '<latest@example.com>',
            false,
            now()
        );

        $records = app(AdminDashboardOverview::class)
            ->recentConversationsQuery()
            ->get();

        $this->assertTrue($records->contains($latest));
        $this->assertFalse($records->contains($older));
    }

    public function test_order_metrics_and_recent_orders_use_real_order_fields(): void
    {
        $oldest = Order::factory()->create([
            'status' => 'pending',
            'created_at' => now()->subDays(2),
        ]);

        $newest = Order::factory()->create([
            'status' => 'contacted',
            'created_at' => now(),
        ]);

        Order::factory()->create([
            'status' => 'pending',
            'created_at' => now()->subDay(),
        ]);

        $overview = app(AdminDashboardOverview::class);

        $this->assertSame([
            'pending_orders' => 2,
            'total_orders' => 3,
        ], $overview->orderMetrics());

        $recent = $overview->recentOrdersQuery(2)->get();

        $this->assertSame($newest->id, $recent->first()->id);
        $this->assertFalse($recent->contains($oldest));
    }

    public function test_quick_actions_respect_resource_authorization(): void
    {
        $support = User::factory()->create(['status' => 'active']);
        $support->assignRole('Support');

        $this->actingAs($support);

        $supportActions = collect(app(AdminDashboardOverview::class)->quickActions())
            ->pluck('label')
            ->all();

        $this->assertSame(['Email Inbox'], $supportActions);

        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        $this->actingAs($superAdmin);

        $superAdminActions = collect(app(AdminDashboardOverview::class)->quickActions())
            ->pluck('label')
            ->all();

        $this->assertSame([
            'Email Inbox',
            'Orders',
            'Site Settings',
            'Pages',
        ], $superAdminActions);
    }

    private function createInboundMessage(
        EmailThread $thread,
        string $messageId,
        bool $isRead,
        mixed $receivedAt = null
    ): EmailMessage {
        return EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'message_id' => $messageId,
            'from_email' => 'sender@example.com',
            'to_email' => 'admin@example.com',
            'subject' => $thread->subject,
            'attachments' => [],
            'metadata' => [],
            'is_read' => $isRead,
            'received_at' => $receivedAt ?? now(),
        ]);
    }
}
