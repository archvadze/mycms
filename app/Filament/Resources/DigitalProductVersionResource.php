<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DigitalProductVersionResource\Pages;
use App\Models\DigitalProductVersion;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\AdminAccess;

class DigitalProductVersionResource extends Resource
{
    protected static ?string $model = DigitalProductVersion::class;

    public static function getNavigationGroup(): ?string { return 'Content'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationLabel = 'Product Versions';
    protected static ?int $navigationSort = 7;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageContent() || AdminAccess::canManageOrders();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Version Details')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Select::make('digital_product_id')
                        ->label('Product')
                        ->relationship('product', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('version_number')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('1.0.0'),
                    Forms\Components\Textarea::make('changelog')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(false),
                ])->columns(2),
            Section::make('File')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\FileUpload::make('file_path')
                        ->label('Product File')
                        ->disk('public')
                        ->directory('products/files')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('version_number')
                    ->label('Version')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
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
            'index'  => Pages\ListDigitalProductVersions::route('/'),
            'create' => Pages\CreateDigitalProductVersion::route('/create'),
            'edit'   => Pages\EditDigitalProductVersion::route('/{record}/edit'),
        ];
    }
}
