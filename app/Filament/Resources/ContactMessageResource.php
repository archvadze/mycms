<?php
namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\AdminAccess;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;
    public static function getNavigationGroup(): ?string { return 'Support'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Contact Messages';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageInbox();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Contact Details')
                ->schema([
                    Forms\Components\TextInput::make('name')->maxLength(255),
                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    Forms\Components\TextInput::make('subject')->maxLength(255)->columnSpanFull(),
                ])->columns(2),
            Section::make('Message')
                ->schema([
                    Forms\Components\Textarea::make('message')->rows(5)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('subject')->limit(40),
                Tables\Columns\TextColumn::make('message')->limit(50),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No contact messages')
            ->emptyStateDescription('Submitted website contact forms will appear here.')
            ->actions([
                Actions\EditAction::make()
                    ->label('Open'),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListContactMessages::route('/'),
            'create' => Pages\CreateContactMessage::route('/create'),
            'edit'   => Pages\EditContactMessage::route('/{record}/edit'),
        ];
    }
}
