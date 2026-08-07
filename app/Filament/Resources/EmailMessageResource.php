<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailMessageResource\Pages;
use App\Models\EmailMessage;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailMessageResource extends Resource
{
    protected static ?string $model = EmailMessage::class;

    protected static \BackedEnum|string|null $navigationIcon =
    'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Inbox';

    protected static ?string $modelLabel = 'Email';

    protected static ?string $pluralModelLabel = 'Inbox';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return 'Operations';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole([
            'Super Admin',
            'Admin',
            'Support',
        ]) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->where('direction', 'inbound')
            ->where('is_read', false)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Email Details')
                ->schema([
                    Forms\Components\TextInput::make('from_name')
                        ->label('From name')
                        ->disabled(),

                    Forms\Components\TextInput::make('from_email')
                        ->label('From')
                        ->disabled(),

                    Forms\Components\TextInput::make('to_email')
                        ->label('To')
                        ->disabled(),

                    Forms\Components\TextInput::make('received_at')
                        ->label('Received')
                        ->disabled()
                        ->formatStateUsing(
                            fn($state) => $state
                                ? \Illuminate\Support\Carbon::parse($state)
                                ->format('M j, Y H:i')
                                : null
                        ),

                    Forms\Components\TextInput::make('subject')
                        ->label('Subject')
                        ->disabled()
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Message')
                ->schema([
                    Forms\Components\Textarea::make('text_body')
                        ->label('')
                        ->rows(18)
                        ->disabled()
                        ->columnSpanFull(),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn(Builder $query) => $query
                    ->where('direction', 'inbound')
            )
            ->columns([
                Tables\Columns\IconColumn::make('is_read')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-envelope-open')
                    ->falseIcon('heroicon-s-envelope')
                    ->sortable(),

                Tables\Columns\TextColumn::make('from_email')
                    ->label('From')
                    ->searchable()
                    ->sortable()
                    ->weight(
                        fn(EmailMessage $record): string =>
                        $record->is_read ? 'regular' : 'bold'
                    ),

                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->limit(60)
                    ->weight(
                        fn(EmailMessage $record): string =>
                        $record->is_read ? 'regular' : 'bold'
                    ),

                Tables\Columns\TextColumn::make('text_body')
                    ->label('Preview')
                    ->limit(70)
                    ->wrap(),

                Tables\Columns\TextColumn::make('received_at')
                    ->label('Received')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->since()
                    ->tooltip(
                        fn(EmailMessage $record): ?string =>
                        $record->received_at?->format('Y-m-d H:i:s')
                    ),
            ])
            ->defaultSort('received_at', 'desc')
            ->actions([
                Actions\ViewAction::make()
                    ->label('Open')
                    ->icon('heroicon-o-envelope-open'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailMessages::route('/'),
            'view' => Pages\ViewEmailMessage::route('/{record}'),
        ];
    }
}
