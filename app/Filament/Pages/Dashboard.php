<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\RecentConversationsWidget;
use App\Filament\Widgets\RecentOrdersWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

class Dashboard extends BaseDashboard
{
    protected static bool $isDiscovered = false;

    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            StatsOverviewWidget::class,
            RecentConversationsWidget::class,
            RecentOrdersWidget::class,
            QuickActionsWidget::class,
        ];
    }
}
