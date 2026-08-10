<?php
namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;
    public static function getNavigationGroup(): ?string { return 'Content'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationLabel = 'Services';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole(['Super Admin', 'Editor']);
    }

    public static function setActive(Service $service, bool $active): void
    {
        $service->update([
            'status' => $active,
            'is_active' => $active,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Service Details')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()->maxLength(255),
                    Forms\Components\TextInput::make('icon')
                        ->maxLength(255)
                        ->placeholder('code, palette, rocket...')
                        ->helperText('Font Awesome icon name without "fa-" prefix'),
                    Forms\Components\TextInput::make('base_price')
                        ->label('Base Price')->numeric()->prefix('$'),
                    Forms\Components\TextInput::make('button_text')
                        ->maxLength(255)->default('Get Started'),
                    Forms\Components\Toggle::make('status')->default(true),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\Textarea::make('description')
                        ->rows(3)->columnSpanFull()
                ])->columns(2),
            Section::make('Service Image')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\FileUpload::make('image')
                        ->label('Service Image')->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->disk('public')->directory('services'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->height(40)->width(60),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('base_price')->money('USD'),
                Tables\Columns\TextColumn::make('icon')->badge()->color('gray'),
                Tables\Columns\IconColumn::make('status')->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Visible'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('preview')
                        ->label('Preview')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(route('services'))
                        ->openUrlInNewTab()
                        ->visible(fn(Service $record): bool => (bool) $record->status),
                    Actions\Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->visible(fn(Service $record): bool => ! $record->status)
                        ->action(fn(Service $record) => static::setActive($record, true)),
                    Actions\Action::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->visible(fn(Service $record): bool => (bool) $record->status)
                        ->action(fn(Service $record) => static::setActive($record, false)),
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
                            fn(Service $record) => static::setActive($record, true)
                        )),
                    Actions\BulkAction::make('deactivate')
                        ->label('Deactivate selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->action(fn($records): mixed => $records->each(
                            fn(Service $record) => static::setActive($record, false)
                        )),
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit'   => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
