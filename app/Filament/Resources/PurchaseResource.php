<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Models\Purchase;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\AdminAccess;
use Illuminate\Database\Eloquent\Builder;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    public static function getNavigationGroup(): ?string { return 'Operations'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Purchases';
    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageOrders();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Purchase Details')
                ->columnSpanFull()
                ->schema([
                    \Filament\Forms\Components\Select::make('user_id')
                        ->label('Customer')
                        ->relationship('user', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    \Filament\Forms\Components\Select::make('digital_product_version_id')
                        ->label('Product Version')
                        ->relationship('version', 'version_number')
                        ->required()
                        ->searchable()
                        ->preload(),
                    \Filament\Forms\Components\TextInput::make('transaction_id')
                        ->required()
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('amount')
                        ->required()
                        ->numeric()
                        ->prefix('$'),
                    \Filament\Forms\Components\TextInput::make('download_limit')
                        ->required()
                        ->numeric()
                        ->default(5),
                    \Filament\Forms\Components\DateTimePicker::make('download_expires_at'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with(['user', 'version.product']))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('version.product.name')
                    ->label('Product')
                    ->searchable(),
                Tables\Columns\TextColumn::make('version.version_number')
                    ->label('Version')
                    ->badge(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('download_limit')
                    ->label('Downloads Left'),
                Tables\Columns\TextColumn::make('download_expires_at')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->placeholder('No expiry'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Purchased')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Customer')
                    ->relationship('user', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('digital_product_version_id')
                    ->label('Product version')
                    ->relationship('version', 'version_number')
                    ->searchable(),
                Tables\Filters\Filter::make('download_state')
                    ->label('Download state')
                    ->schema([
                        Forms\Components\Select::make('state')
                            ->options([
                                'available' => 'Available',
                                'expired' => 'Expired',
                                'depleted' => 'No downloads left',
                            ]),
                    ])
                    ->query(fn(Builder $query, array $data): Builder => match ($data['state'] ?? null) {
                        'available' => $query
                            ->where('download_limit', '>', 0)
                            ->where(fn(Builder $query): Builder => $query
                                ->whereNull('download_expires_at')
                                ->orWhere('download_expires_at', '>=', now())),
                        'expired' => $query->whereNotNull('download_expires_at')->where('download_expires_at', '<', now()),
                        'depleted' => $query->where('download_limit', '<=', 0),
                        default => $query,
                    }),
            ])
            ->emptyStateHeading('No purchases')
            ->emptyStateDescription('Completed digital product purchases will appear here.')
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'edit'   => Pages\EditPurchase::route('/{record}/edit'),
        ];
    }
}
