<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EmailMessageResource;
use App\Models\EmailMessage;
use App\Services\AdminDashboardOverview;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentConversationsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Conversations';

    public static function canView(): bool
    {
        return EmailMessageResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        /** @var AdminDashboardOverview $overview */
        $overview = app(AdminDashboardOverview::class);

        return $table
            ->query(fn(): Builder => $overview->recentConversationsQuery())
            ->paginated(false)
            ->columns([
                Tables\Columns\IconColumn::make('is_read')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-envelope-open')
                    ->falseIcon('heroicon-s-envelope'),
                Tables\Columns\TextColumn::make('from_email')
                    ->label('Sender')
                    ->limit(30)
                    ->weight(
                        fn(EmailMessage $record): string =>
                        $record->is_read ? 'regular' : 'bold'
                    ),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->limit(48)
                    ->weight(
                        fn(EmailMessage $record): string =>
                        $record->is_read ? 'regular' : 'bold'
                    ),
                Tables\Columns\TextColumn::make('thread.status')
                    ->label('Status')
                    ->badge()
                    ->color(
                        fn(?string $state): string =>
                        $state === 'closed' ? 'gray' : 'success'
                    ),
                Tables\Columns\TextColumn::make('received_at')
                    ->label('Latest')
                    ->since()
                    ->sortable(),
            ])
            ->recordUrl(
                fn(EmailMessage $record): string =>
                EmailMessageResource::getUrl('view', ['record' => $record])
            );
    }
}
