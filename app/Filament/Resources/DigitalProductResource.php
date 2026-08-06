<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DigitalProductResource\Pages;
use App\Models\DigitalProduct;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;

class DigitalProductResource extends Resource
{
    protected static ?string $model = DigitalProduct::class;

    public static function getNavigationGroup(): ?string { return 'Content'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Digital Products';
    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole(['Super Admin', 'Admin', 'Editor']);
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
                        ->afterStateUpdated(fn ($state, callable $set) =>
                            $set('slug', \Illuminate\Support\Str::slug($state))
                        ),
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
                        ->prefix('$'),
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
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_published')->boolean(),
                Tables\Columns\IconColumn::make('is_featured')->boolean(),
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
            'index'  => Pages\ListDigitalProducts::route('/'),
            'create' => Pages\CreateDigitalProduct::route('/create'),
            'edit'   => Pages\EditDigitalProduct::route('/{record}/edit'),
        ];
    }
}
