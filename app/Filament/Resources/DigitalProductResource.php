<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DigitalProductResource\Pages;
use App\Models\DigitalProduct;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\AdminAccess;
use Illuminate\Support\Str;

class DigitalProductResource extends Resource
{
    protected static ?string $model = DigitalProduct::class;

    public static function getNavigationGroup(): ?string { return 'Content'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Digital Products';
    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageContent() || AdminAccess::canManageOrders();
    }

    public static function setPublished(DigitalProduct $product, bool $published): void
    {
        $product->update(['is_published' => $published]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Product Information')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                            if (blank($get('slug'))) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('category')
                        ->options([
                            'wordpress-themes'   => 'WordPress Themes',
                            'wordpress-plugins'  => 'WordPress Plugins',
                            'ui-kits'            => 'UI Kits',
                            'templates'          => 'Templates',
                            'scripts'            => 'Scripts',
                            'graphics'           => 'Graphics',
                            'other'              => 'Other',
                        ])
                        ->required()
                        ->searchable(),
                    Forms\Components\Select::make('user_id')
                        ->label('Owner')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Textarea::make('short_description')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('description')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\TagsInput::make('tags')
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Cover Image')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\FileUpload::make('image')
                        ->label('Cover Image')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->disk('public')
                        ->directory('products/covers')
                        ->imageResizeMode('cover')
                        ->imageEditor()
                        ->columnSpanFull(),
                ]),

            Section::make('Gallery')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\FileUpload::make('gallery_images')
                        ->label('Gallery Images')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->multiple()
                        ->disk('public')
                        ->directory('products/gallery')
                        ->maxFiles(5)
                        ->imageEditor()
                        ->columnSpanFull(),
                ]),

            Section::make('Pricing')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('price')
                        ->required()
                        ->numeric()
                        ->prefix('$'),
                    Forms\Components\TextInput::make('sale_price')
                        ->numeric()
                        ->prefix('$')
                        ->lt('price'),
                    Forms\Components\TextInput::make('demo_url')
                        ->label('Demo URL')
                        ->url()
                        ->maxLength(255),
                ])->columns(3),

            Section::make('Visibility')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Toggle::make('is_published')
                        ->label('Published')
                        ->default(false),
                    Forms\Components\Toggle::make('is_featured')
                        ->label('Featured')
                        ->default(false),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Cover')
                    ->disk('public')
                    ->height(50)
                    ->width(80),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_published')->boolean(),
                Tables\Columns\IconColumn::make('is_featured')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'wordpress-themes'   => 'WordPress Themes',
                        'wordpress-plugins'  => 'WordPress Plugins',
                        'ui-kits'            => 'UI Kits',
                        'templates'          => 'Templates',
                        'scripts'            => 'Scripts',
                        'graphics'           => 'Graphics',
                        'other'              => 'Other',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('preview')
                        ->label('Preview')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn(DigitalProduct $record): string => route('shop.show', $record->slug))
                        ->openUrlInNewTab()
                        ->visible(fn(DigitalProduct $record): bool => (bool) $record->is_published),
                    Actions\Action::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->visible(fn(DigitalProduct $record): bool => ! $record->is_published)
                        ->action(fn(DigitalProduct $record) => static::setPublished($record, true)),
                    Actions\Action::make('unpublish')
                        ->label('Unpublish')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->visible(fn(DigitalProduct $record): bool => (bool) $record->is_published)
                        ->action(fn(DigitalProduct $record) => static::setPublished($record, false)),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make(),
                ])->label('Actions')->icon('heroicon-m-ellipsis-vertical')->iconButton(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('publish')
                        ->label('Publish selected')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(fn($records): mixed => $records->each(
                            fn(DigitalProduct $record) => static::setPublished($record, true)
                        )),
                    Actions\BulkAction::make('unpublish')
                        ->label('Unpublish selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->action(fn($records): mixed => $records->each(
                            fn(DigitalProduct $record) => static::setPublished($record, false)
                        )),
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDigitalProducts::route('/'),
            'create' => Pages\CreateDigitalProduct::route('/create'),
            'edit'   => Pages\EditDigitalProduct::route('/{record}/edit'),
        ];
    }
}
