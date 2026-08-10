<?php
namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Services\AdminDashboardOverview;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentOrdersWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Orders';

    public static function canView(): bool
    {
        return OrderResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        /** @var AdminDashboardOverview $overview */
        $overview = app(AdminDashboardOverview::class);

        return $table
            ->query(fn(): Builder => $overview->recentOrdersQuery())
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->width(60),
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Client')
                    ->searchable()
                    ->limit(28),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->limit(32)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn(?string $state): string =>
                        OrderResource::statusLabel($state)
                    )
                    ->color(
                        fn(?string $state): string =>
                        OrderResource::statusColor($state)
                    ),
                Tables\Columns\TextColumn::make('price_estimate')
                    ->money('USD')
                    ->label('Estimate'),
                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->label('Created')
                    ->sortable(),
            ])
            ->recordUrl(
                fn($record): string =>
                OrderResource::getUrl('view', ['record' => $record])
            );
    }
}
