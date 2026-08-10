<?php
namespace App\Filament\Widgets;

use App\Filament\Resources\EmailMessageResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ProjectResource;
use App\Services\AdminDashboardOverview;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return EmailMessageResource::canViewAny()
            || OrderResource::canViewAny()
            || ProjectResource::canViewAny();
    }

    protected function getStats(): array
    {
        /** @var AdminDashboardOverview $overview */
        $overview = app(AdminDashboardOverview::class);

        $stats = [];

        if (EmailMessageResource::canViewAny()) {
            $email = $overview->emailMetrics();

            $stats[] = Stat::make(
                'Open email conversations',
                $email['open_conversations']
            )
                ->description('Threads with open status')
                ->descriptionIcon('heroicon-o-envelope')
                ->color('primary')
                ->url(EmailMessageResource::getUrl());

            $stats[] = Stat::make(
                'Unread email conversations',
                $email['unread_conversations']
            )
                ->description('Latest inbound message is unread')
                ->descriptionIcon('heroicon-o-envelope')
                ->color('danger')
                ->url(EmailMessageResource::getUrl());
        }

        if (OrderResource::canViewAny()) {
            $orders = $overview->orderMetrics();

            $stats[] = Stat::make('Pending orders', $orders['pending_orders'])
                ->description('Orders with pending status')
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('warning')
                ->url(OrderResource::getUrl());

            $stats[] = Stat::make('Total orders', $orders['total_orders'])
                ->description('All stored orders')
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('gray')
                ->url(OrderResource::getUrl());
        }

        if (ProjectResource::canViewAny()) {
            $projects = $overview->projectMetrics();

            $stats[] = Stat::make('Active projects', $projects['active_projects'])
                ->description('Projects in progress')
                ->descriptionIcon('heroicon-o-folder')
                ->color('info')
                ->url(ProjectResource::getUrl());
        }

        return $stats;
    }
}
