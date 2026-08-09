<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardOverview;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.quick-actions-widget';

    public static function canView(): bool
    {
        return app(AdminDashboardOverview::class)->quickActions() !== [];
    }

    protected function getViewData(): array
    {
        return [
            'actions' => app(AdminDashboardOverview::class)->quickActions(),
        ];
    }
}
