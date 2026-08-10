<?php
namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\MenuItem;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\AdminAccess;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;
    public static function getNavigationGroup(): ?string { return 'System'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bars-3';
    protected static ?string $navigationLabel = 'Menu Items';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageSystemContent();
    }

    public static function setActive(MenuItem $menuItem, bool $active): void
    {
        $menuItem->update(['is_active' => $active]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Menu Item')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->required()->maxLength(255)->placeholder('e.g., About Us'),
                    Forms\Components\TextInput::make('url')
                        ->required()->maxLength(255)->placeholder('/about'),
                    Forms\Components\Select::make('location')
                        ->options([
                            'header' => 'Header Navigation',
                            'footer' => 'Footer Quick Links',
                            'bottom' => 'Footer Bottom Bar',
                        ])
                        ->default('header')->required(),
                    Forms\Components\TextInput::make('position')
                        ->numeric()->default(0)
                        ->helperText('Lower = first'),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\Toggle::make('open_in_new_tab')->default(false),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('position')->sortable()->width(60),
                Tables\Columns\TextColumn::make('label')->searchable(),
                Tables\Columns\TextColumn::make('url')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('location')->badge()
                    ->color(fn($state) => match($state) {
                        'header' => 'info', 'footer' => 'gray', 'bottom' => 'warning', default => 'gray'
                    }),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location')
                    ->options([
                        'header' => 'Header Navigation',
                        'footer' => 'Footer Quick Links',
                        'bottom' => 'Footer Bottom Bar',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('open')
                        ->label('Open')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn(MenuItem $record): string => url($record->url))
                        ->openUrlInNewTab(),
                    Actions\Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->visible(fn(MenuItem $record): bool => ! $record->is_active)
                        ->action(fn(MenuItem $record) => static::setActive($record, true)),
                    Actions\Action::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->visible(fn(MenuItem $record): bool => (bool) $record->is_active)
                        ->action(fn(MenuItem $record) => static::setActive($record, false)),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make(),
                ])->label('Actions')->icon('heroicon-m-ellipsis-vertical')->iconButton(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('activate')
                        ->label('Activate selected')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(fn($records): mixed => $records->each(
                            fn(MenuItem $record) => static::setActive($record, true)
                        )),
                    Actions\BulkAction::make('deactivate')
                        ->label('Deactivate selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->action(fn($records): mixed => $records->each(
                            fn(MenuItem $record) => static::setActive($record, false)
                        )),
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit'   => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }
}
