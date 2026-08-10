<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Activitylog\Models\Activity;
use App\Support\AdminAccess;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    public static function getNavigationGroup(): ?string { return 'Operations'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageOrders();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    protected static ?string $navigationLabel = 'Orders';

    public static function statusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'contacted' => 'Contacted',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return static::statusOptions()[$status] ?? ucfirst((string) $status);
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'contacted' => 'info',
            'accepted' => 'success',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    public static function canTransitionStatus(Order $order, string $status): bool
    {
        if (! array_key_exists($status, static::statusOptions())) {
            return false;
        }

        if ($order->status === $status) {
            return false;
        }

        return $order->status !== 'accepted';
    }

    public static function updateStatus(Order $order, string $status): void
    {
        if (! static::canEdit($order)) {
            throw new AuthorizationException();
        }

        if (! array_key_exists($status, static::statusOptions())) {
            throw new InvalidArgumentException("Unsupported order status [{$status}].");
        }

        if ($order->status === $status) {
            return;
        }

        if (! static::canTransitionStatus($order, $status)) {
            throw new InvalidArgumentException('Accepted orders cannot be changed.');
        }

        $order->update(['status' => $status]);
    }

    public static function relatedOrdersByEmail(Order $order, int $limit = 5): Builder
    {
        return Order::query()
            ->where('email', $order->email)
            ->whereKeyNot($order->id)
            ->latest()
            ->limit($limit);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Client Info')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('client_name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')->tel()->maxLength(255),
                    Forms\Components\Select::make('client_id')
                        ->relationship('client', 'name')
                        ->searchable()->preload(),
                ])->columns(2),
            Section::make('Order Details')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('domain')->required()->maxLength(255),
                    Forms\Components\Select::make('website_type')
                        ->options([
                            'business'     => 'Business Website',
                            'e-commerce'   => 'E-commerce',
                            'portfolio'    => 'Portfolio',
                            'blog'         => 'Blog',
                            'landing-page' => 'Landing Page',
                            'other'        => 'Other',
                        ])->required(),
                    Forms\Components\Select::make('timeline')
                        ->options([
                            'asap'       => 'ASAP',
                            '1-month'    => '1 Month',
                            '2-3-months' => '2-3 Months',
                            '3-6-months' => '3-6 Months',
                            'flexible'   => 'Flexible',
                        ]),
                    Forms\Components\Select::make('budget_range')
                        ->options([
                            'under-5k' => 'Under $5,000',
                            '5k-10k'   => '$5,000 - $10,000',
                            '10k-25k'  => '$10,000 - $25,000',
                            '25k-50k'  => '$25,000 - $50,000',
                            'over-50k' => 'Over $50,000',
                        ]),
                    Forms\Components\Select::make('status')
                        ->options(static::statusOptions())
                        ->required()
                        ->default('pending'),
                    Forms\Components\TextInput::make('price_estimate')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix('$')
                        ->label('Estimated Total'),
                ])->columns(2),
            Section::make('Project Description')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Textarea::make('project_description')->rows(4)->columnSpanFull(),
                    Forms\Components\Textarea::make('additional_requirements')->rows(3)->columnSpanFull(),
                ])->collapsed(),
            Section::make('Services & Features')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\CheckboxList::make('services')
                        ->relationship('services', 'name')->columns(2),
                    Forms\Components\CheckboxList::make('features')
                        ->relationship('features', 'name')->columns(2),
                ])->columns(2)->collapsed(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Status')
                ->schema([
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(
                            fn(?string $state): string => static::statusLabel($state)
                        )
                        ->color(
                            fn(?string $state): string => static::statusColor($state)
                        ),
                    TextEntry::make('payment_status')
                        ->label('Payment')
                        ->badge()
                        ->color(
                            fn(?string $state): string =>
                            $state === 'paid' ? 'success' : 'gray'
                        ),
                    TextEntry::make('price_estimate')
                        ->label('Estimate')
                        ->money('USD'),
                    TextEntry::make('paid_at')
                        ->label('Paid at')
                        ->dateTime('M j, Y H:i')
                        ->placeholder('Not paid'),
                ])
                ->columns(4),

            Section::make('Customer / Contact')
                ->schema([
                    TextEntry::make('client_name')
                        ->label('Name'),
                    TextEntry::make('email')
                        ->label('Email')
                        ->copyable(),
                    TextEntry::make('phone')
                        ->label('Phone')
                        ->placeholder('No phone'),
                    TextEntry::make('client.name')
                        ->label('Linked client')
                        ->placeholder('No linked client'),
                ])
                ->columns(2),

            Section::make('Order / Request Details')
                ->schema([
                    TextEntry::make('id')
                        ->label('Order ID')
                        ->formatStateUsing(fn($state): string => '#' . $state),
                    TextEntry::make('domain')
                        ->label('Domain'),
                    TextEntry::make('website_type')
                        ->label('Website type')
                        ->formatStateUsing(
                            fn(?string $state): string =>
                            $state ? str($state)->replace('-', ' ')->title()->toString() : '-'
                        ),
                    TextEntry::make('timeline')
                        ->placeholder('Not specified'),
                    TextEntry::make('budget_range')
                        ->label('Budget range')
                        ->placeholder('Not specified'),
                    TextEntry::make('services.name')
                        ->label('Services')
                        ->listWithLineBreaks()
                        ->placeholder('No services'),
                    TextEntry::make('features.name')
                        ->label('Features')
                        ->listWithLineBreaks()
                        ->placeholder('No features'),
                    TextEntry::make('project_description')
                        ->label('Project description')
                        ->columnSpanFull()
                        ->placeholder('No description'),
                    TextEntry::make('additional_requirements')
                        ->label('Additional requirements')
                        ->columnSpanFull()
                        ->placeholder('No additional requirements'),
                ])
                ->columns(3),

            Section::make('Dates')
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Created')
                        ->dateTime('M j, Y H:i'),
                    TextEntry::make('updated_at')
                        ->label('Updated')
                        ->dateTime('M j, Y H:i'),
                    TextEntry::make('payment_id')
                        ->label('Payment ID')
                        ->placeholder('No payment ID'),
                    TextEntry::make('payment_method')
                        ->label('Payment method')
                        ->placeholder('No payment method'),
                ])
                ->columns(4),

            Section::make('Other Orders From This Email')
                ->schema([
                    RepeatableEntry::make('related_orders')
                        ->label('')
                        ->state(function (Order $record): array {
                            return static::relatedOrdersByEmail($record)
                                ->get()
                                ->map(fn(Order $order): array => [
                                    'id' => $order->id,
                                    'status' => $order->status,
                                    'price_estimate' => $order->price_estimate,
                                    'created_at' => $order->created_at?->format('M j, Y'),
                                    'url' => static::getUrl('view', ['record' => $order]),
                                ])
                                ->all();
                        })
                        ->schema([
                            TextEntry::make('id')
                                ->label('Order')
                                ->formatStateUsing(fn($state): string => '#' . $state),
                            TextEntry::make('status')
                                ->badge()
                                ->formatStateUsing(
                                    fn(?string $state): string => static::statusLabel($state)
                                )
                                ->color(
                                    fn(?string $state): string => static::statusColor($state)
                                ),
                            TextEntry::make('price_estimate')
                                ->label('Estimate')
                                ->money('USD'),
                            TextEntry::make('created_at')
                                ->label('Created'),
                            TextEntry::make('url')
                                ->label('')
                                ->formatStateUsing(fn(): string => 'Open')
                                ->url(fn($state): ?string => $state),
                        ])
                        ->columns(4)
                        ->columnSpanFull(),
                ])
                ->visible(
                    fn(Order $record): bool =>
                    static::relatedOrdersByEmail($record, 1)->exists()
                ),

            Section::make('Activity')
                ->schema([
                    RepeatableEntry::make('activity')
                        ->label('')
                        ->state(function (Order $record): array {
                            return Activity::query()
                                ->where('subject_type', Order::class)
                                ->where('subject_id', $record->id)
                                ->latest()
                                ->limit(5)
                                ->get()
                                ->map(fn(Activity $activity): array => [
                                    'description' => $activity->description,
                                    'changes' => $activity->properties->toJson(),
                                    'created_at' => $activity->created_at?->format('M j, Y H:i'),
                                ])
                                ->all();
                        })
                        ->schema([
                            TextEntry::make('description')
                                ->label('Action'),
                            TextEntry::make('changes')
                                ->label('Changes')
                                ->limit(120),
                            TextEntry::make('created_at')
                                ->label('Time'),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                ])
                ->visible(
                    fn(Order $record): bool =>
                    Activity::query()
                        ->where('subject_type', Order::class)
                        ->where('subject_id', $record->id)
                        ->exists()
                ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with('client'))
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->searchable()
                    ->width(60),
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Customer')
                    ->searchable()
                    ->limit(26),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->limit(28)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn(?string $state): string => static::statusLabel($state)
                    )
                    ->color(
                        fn(?string $state): string => static::statusColor($state)
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_estimate')
                    ->label('Estimate')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(static::statusOptions()),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'name')
                    ->searchable(),
                Tables\Filters\Filter::make('created_at')
                    ->schema([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created from'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn(Builder $query, string $date): Builder =>
                                $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn(Builder $query, string $date): Builder =>
                                $query->whereDate('created_at', '<=', $date)
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('New client requests will appear here for review and follow-up.')
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    static::statusAction('contacted', 'Mark contacted')
                        ->visible(
                            fn(Order $record): bool =>
                            static::canEdit($record)
                            && static::canTransitionStatus($record, 'contacted')
                        ),
                    static::statusAction('accepted', 'Accept order')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription(
                            'Accepting an order may create a project through the existing order observer.'
                        )
                        ->visible(
                            fn(Order $record): bool =>
                            static::canEdit($record)
                            && static::canTransitionStatus($record, 'accepted')
                        ),
                    static::statusAction('rejected', 'Reject order')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(
                            fn(Order $record): bool =>
                            static::canEdit($record)
                            && static::canTransitionStatus($record, 'rejected')
                        ),
                    Actions\DeleteAction::make(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function statusAction(string $status, string $label): Actions\Action
    {
        return Actions\Action::make('status_' . $status)
            ->label($label)
            ->icon(match ($status) {
                'contacted' => 'heroicon-o-phone',
                'accepted' => 'heroicon-o-check-circle',
                'rejected' => 'heroicon-o-x-circle',
                default => 'heroicon-o-arrow-path',
            })
            ->color(static::statusColor($status))
            ->action(function (Order $record) use ($status): void {
                static::updateStatus($record, $status);
            })
            ->authorize(fn(Order $record): bool => static::canEdit($record))
            ->successNotificationTitle(
                'Order marked ' . strtolower(static::statusLabel($status))
            );
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view'   => Pages\ViewOrder::route('/{record}'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
