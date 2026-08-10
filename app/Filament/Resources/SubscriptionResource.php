<?php
namespace App\Filament\Resources;

use App\Filament\Resources\Subscriptions\Pages;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use App\Support\AdminAccess;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Subscriptions';
    protected static ?int $navigationSort = 7;

    public static function getNavigationGroup(): ?string
    {
        return 'Operations';
    }

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageSubscriptions();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Subscription::where('cancel_requested', true)->where('status', 'active')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()->preload()->required(),
                Forms\Components\Select::make('subscription_plan_id')
                    ->relationship('plan', 'name')
                    ->searchable()->preload()->required()->label('Plan'),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'active'    => 'Active',
                        'cancelled' => 'Cancelled',
                        'expired'   => 'Expired',
                    ])->required(),
                Forms\Components\DatePicker::make('starts_at'),
                Forms\Components\DatePicker::make('ends_at'),
                Forms\Components\DatePicker::make('next_invoice_at')->label('Next Invoice'),
                Forms\Components\Toggle::make('cancel_requested')->label('Cancel Requested'),
                Forms\Components\Textarea::make('notes')->rows(3)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with(['user', 'plan']))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('plan.name')->badge()->color('primary'),
                Tables\Columns\TextColumn::make('plan.price')->money('EUR')->label('Price'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn(string $state) => match($state) {
                        'active'    => 'success',
                        'pending'   => 'warning',
                        'cancelled' => 'danger',
                        'expired'   => 'gray',
                    }),
                Tables\Columns\IconColumn::make('cancel_requested')
                    ->boolean()->label('Cancel Req.')
                    ->trueColor('danger')->falseColor('gray'),
                Tables\Columns\TextColumn::make('next_invoice_at')->date()->label('Next Invoice'),
                Tables\Columns\TextColumn::make('ends_at')->date()->label('Ends'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'active'    => 'Active',
                        'cancelled' => 'Cancelled',
                        'expired'   => 'Expired',
                    ]),
                Tables\Filters\TernaryFilter::make('cancel_requested')->label('Cancel Requested'),
                Tables\Filters\SelectFilter::make('subscription_plan_id')
                    ->label('Plan')
                    ->relationship('plan', 'name')
                    ->searchable(),
            ])
            ->emptyStateHeading('No subscriptions')
            ->emptyStateDescription('Client subscription requests and active plans will appear here.')
            ->actions([
                Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Subscription $r) => $r->status === 'pending')
                    ->action(function(Subscription $r) {
                        $r->update([
                            'status'           => 'active',
                            'starts_at'        => Carbon::today(),
                            'next_invoice_at'  => Carbon::today()->addMonth(),
                            'ends_at'          => null,
                        ]);
                    }),
                Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Subscription $r) => $r->status === 'active')
                    ->requiresConfirmation()
                    ->action(fn(Subscription $r) => $r->update([
                        'status'   => 'cancelled',
                        'ends_at'  => Carbon::today(),
                    ])),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit'   => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
