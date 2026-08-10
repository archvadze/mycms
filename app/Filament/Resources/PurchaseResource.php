<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Models\Purchase;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\AdminAccess;

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
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
