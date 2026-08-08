<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailMessageResource\Pages;
use App\Models\EmailMessage;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
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

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope';

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
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('email_thread_id')
                    ->orWhereIn('id', function ($subQuery): void {
                        $subQuery
                            ->selectRaw('MAX(id)')
                            ->from('email_messages')
                            ->where('direction', 'inbound')
                            ->whereNotNull('email_thread_id')
                            ->groupBy('email_thread_id');
                    });
            })
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


            Section::make('Attachments')
                ->schema([
                    RepeatableEntry::make('attachment_items')
                        ->label('')
                        ->state(function (EmailMessage $record): array {
                            return collect($record->attachments ?? [])
                                ->map(function (array $attachment) use ($record): array {
                                    return [
                                        'filename' => $attachment['filename'] ?? 'attachment',
                                        'content_type' => $attachment['content_type'] ?? null,
                                        'size' => $attachment['size'] ?? null,
                                        'download_url' => ! empty($attachment['id'])
                                            ? route(
                                                'email-messages.attachments.download',
                                                [
                                                    'message' => $record->id,
                                                    'attachmentId' => $attachment['id'],
                                                ]
                                            )
                                            : null,
                                    ];
                                })
                                ->all();
                        })
                        ->schema([
                            TextEntry::make('filename')
                                ->label('File')
                                ->url(fn($record): ?string => $record['download_url'] ?? null)
                                ->openUrlInNewTab(),

                            TextEntry::make('content_type')
                                ->label('Type'),

                            TextEntry::make('size')
                                ->label('Size')
                                ->formatStateUsing(
                                    fn($state): string =>
                                    is_numeric($state)
                                        ? number_format(((int) $state) / 1024, 1) . ' KB'
                                        : '—'
                                ),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->columnSpanFull()
                ->visible(
                    fn(EmailMessage $record): bool =>
                    count($record->attachments ?? []) > 0
                ),

            Section::make('Conversation (Full email thread in chronological order)')
                ->schema([
                    RepeatableEntry::make('conversation')
                        ->label('')
                        ->state(function (EmailMessage $record): array {
                            if (! $record->thread) {
                                return [];
                            }

                            return $record->thread
                                ->messages()
                                ->orderByRaw(
                                    'COALESCE(received_at, sent_at, created_at) ASC'
                                )
                                ->get()
                                ->map(function (EmailMessage $message): array {
                                    $timestamp = $message->received_at
                                        ?? $message->sent_at
                                        ?? $message->created_at;

                                    return [
                                        'direction' => $message->direction,
                                        'body' => $message->text_body
                                            ?: strip_tags($message->html_body ?? ''),
                                        'timestamp' => $timestamp?->format('M j, Y H:i'),
                                        'attachment_count' => count($message->attachments ?? []),
                                    ];
                                })
                                ->all();
                        })
                        ->schema([
                            TextEntry::make('direction')
                                ->label('Direction')
                                ->formatStateUsing(
                                    fn(string $state): string =>
                                    $state === 'outbound' ? 'Sent' : 'Received'
                                )
                                ->inlineLabel(),

                            TextEntry::make('timestamp')
                                ->label('Date')
                                ->inlineLabel(),

                            TextEntry::make('body')
                                ->label('Message')
                                ->columnSpanFull(),

                            TextEntry::make('attachment_count')
                                ->label('Attachments')
                                ->formatStateUsing(
                                    fn($state): string =>
                                    $state === 1
                                        ? '1 attachment'
                                        : "{$state} attachments"
                                )
                                ->visible(fn($state): bool => (int) $state > 0)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->columnSpanFull()
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn(Builder $query) => $query
                    ->where('direction', 'inbound')
                    ->where(function (Builder $query): void {
                        $query
                            ->whereNull('email_thread_id')
                            ->orWhereIn('id', function ($subQuery): void {
                                $subQuery
                                    ->selectRaw('MAX(id)')
                                    ->from('email_messages')
                                    ->where('direction', 'inbound')
                                    ->whereNotNull('email_thread_id')
                                    ->groupBy('email_thread_id');
                            });
                    })
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
