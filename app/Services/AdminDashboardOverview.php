<?php

namespace App\Services;

use App\Filament\Resources\EmailMessageResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\SiteSettingResource;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

class AdminDashboardOverview
{
    public function emailMetrics(): array
    {
        return [
            'open_conversations' => EmailThread::query()
                ->where('status', 'open')
                ->count(),
            'unread_conversations' => $this->latestInboundConversationQuery()
                ->where('is_read', false)
                ->count(),
        ];
    }

    public function orderMetrics(): array
    {
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending_orders' => (int) ($counts['pending'] ?? 0),
            'total_orders' => (int) $counts->sum(),
        ];
    }

    public function latestInboundConversationQuery(): Builder
    {
        return EmailMessage::query()
            ->where('direction', 'inbound')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('email_thread_id')
                    ->orWhereIn('id', function ($subQuery): void {
                        $subQuery
                            ->selectRaw('MAX(id)')
                            ->from('email_messages')
                            ->where('direction', 'inbound')
                            ->whereNotNull('email_thread_id')
                            ->groupBy('email_thread_id');
                    });
            });
    }

    public function recentConversationsQuery(int $limit = 8): Builder
    {
        return $this->latestInboundConversationQuery()
            ->with('thread')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit($limit);
    }

    public function recentOrdersQuery(int $limit = 8): Builder
    {
        return Order::query()
            ->latest()
            ->limit($limit);
    }

    public function quickActions(): array
    {
        return array_values(array_filter([
            EmailMessageResource::canViewAny() ? [
                'label' => 'Email Inbox',
                'description' => 'Open conversations and unread mail',
                'icon' => 'heroicon-o-envelope',
                'url' => EmailMessageResource::getUrl(),
            ] : null,
            OrderResource::canViewAny() ? [
                'label' => 'Orders',
                'description' => 'Recent requests and status follow-up',
                'icon' => 'heroicon-o-shopping-bag',
                'url' => OrderResource::getUrl(),
            ] : null,
            SiteSettingResource::canViewAny() ? [
                'label' => 'Site Settings',
                'description' => 'Global CMS settings',
                'icon' => 'heroicon-o-cog',
                'url' => SiteSettingResource::getUrl(),
            ] : null,
            PageResource::canViewAny() ? [
                'label' => 'Pages',
                'description' => 'Primary page content',
                'icon' => 'heroicon-o-document-text',
                'url' => PageResource::getUrl(),
            ] : null,
        ]));
    }
}
