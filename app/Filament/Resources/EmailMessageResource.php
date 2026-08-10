<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailMessageResource\Pages;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Services\Mail\AttachmentPolicy;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Support\AdminAccess;



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
        return AdminAccess::canManageInbox();
    }

    public static function canView(Model $record): bool
    {
        return AdminAccess::canManageInbox();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canManageInbox();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
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

    public static function markConversationRead(EmailMessage $record): void
    {
        if (! $record->thread) {
            $record->update(['is_read' => true]);

            return;
        }

        $record->thread
            ->messages()
            ->where('direction', 'inbound')
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public static function markConversationUnread(EmailMessage $record): void
    {
        if (! $record->thread) {
            $record->update(['is_read' => false]);

            return;
        }

        $record->thread
            ->messages()
            ->where('direction', 'inbound')
            ->update(['is_read' => false]);
    }

    public static function updateConversationStatus(
        EmailMessage $record,
        string $status
    ): void {
        if (! static::canEdit($record)) {
            throw new AuthorizationException();
        }

        $record->thread?->update(['status' => $status]);
    }

    public static function markConversationsRead(Collection $records): void
    {
        static::updateConversationReadState($records, true);
    }

    public static function markConversationsUnread(Collection $records): void
    {
        static::updateConversationReadState($records, false);
    }

    public static function updateConversationsStatus(
        Collection $records,
        string $status
    ): void {
        $threadIds = $records
            ->pluck('email_thread_id')
            ->filter()
            ->unique()
            ->values();

        if ($threadIds->isEmpty()) {
            return;
        }

        EmailThread::query()
            ->whereIn('id', $threadIds)
            ->update(['status' => $status]);
    }

    private static function updateConversationReadState(
        Collection $records,
        bool $isRead
    ): void {
        $threadIds = $records
            ->pluck('email_thread_id')
            ->filter()
            ->unique()
            ->values();

        if ($threadIds->isNotEmpty()) {
            EmailMessage::query()
                ->whereIn('email_thread_id', $threadIds)
                ->where('direction', 'inbound')
                ->update(['is_read' => $isRead]);
        }

        $messageIds = $records
            ->filter(
                fn(EmailMessage $record): bool =>
                $record->email_thread_id === null
            )
            ->pluck('id');

        if ($messageIds->isNotEmpty()) {
            EmailMessage::query()
                ->whereIn('id', $messageIds)
                ->update(['is_read' => $isRead]);
        }
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
                            /** @var AttachmentPolicy $attachmentPolicy */
                            $attachmentPolicy = app(AttachmentPolicy::class);

                            $attachments = $record->attachments ?? [];

                            $batchPolicy = $attachmentPolicy
                                ->evaluateBatchLimits($attachments);

                            return collect($attachments)
                                ->map(function (array $attachment) use (
                                    $record,
                                    $attachmentPolicy,
                                    $batchPolicy
                                ): array {
                                    $filePolicy = $attachmentPolicy
                                        ->evaluateAttachment($attachment);

                                    $allowed = $batchPolicy['allowed']
                                        && $filePolicy['allowed'];

                                    $blockedReason = ! $batchPolicy['allowed']
                                        ? $batchPolicy['reason']
                                        : $filePolicy['reason'];

                                    return [
                                        'filename' => $attachment['filename'] ?? 'attachment',
                                        'content_type' => $attachment['content_type'] ?? null,
                                        'size' => $attachment['size'] ?? null,
                                        'allowed' => $allowed,
                                        'blocked_reason' => $blockedReason,

                                        'download_url' =>
                                        $allowed
                                            && ! empty($attachment['id'])
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
                                ->label('File'),

                            TextEntry::make('download_url')
                                ->label('Download')
                                ->formatStateUsing(fn(): string => 'Download file')
                                ->url(fn($state): ?string => $state)
                                ->openUrlInNewTab()
                                ->visible(fn($state): bool => filled($state)),

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

                            TextEntry::make('blocked_reason')
                                ->label('Security')
                                ->formatStateUsing(
                                    fn($state): string => $state
                                        ? 'Blocked: ' . $state
                                        : 'Allowed - not malware scanned'
                                )
                                ->badge()
                                ->color(
                                    fn($state): string =>
                                    $state ? 'danger' : 'success'
                                )
                                ->columnSpanFull(),
                        ])
                        ->columns(4)
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
                    ->with('thread')
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
                    ->limit(28)
                    ->tooltip(
                        fn(EmailMessage $record): string =>
                        $record->from_email
                    )
                    ->weight(
                        fn(EmailMessage $record): string =>
                        $record->is_read ? 'regular' : 'bold'
                    ),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(42)
                    ->tooltip(
                        fn(EmailMessage $record): string =>
                        $record->subject ?? ''
                    )
                    ->weight(
                        fn(EmailMessage $record): string =>
                        $record->is_read ? 'regular' : 'bold'
                    ),

                Tables\Columns\TextColumn::make('received_at')
                    ->label('Received')
                    ->dateTime('M j, H:i')
                    ->sortable()
                    ->since()
                    ->tooltip(
                        fn(EmailMessage $record): ?string =>
                        $record->received_at?->format('Y-m-d H:i:s')
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('conversation_status')
                    ->label('Status')
                    ->options(function (): array {
                        $counts = EmailThread::query()
                            ->selectRaw('status, COUNT(*) as total')
                            ->groupBy('status')
                            ->pluck('total', 'status');

                        return [
                            'open' => 'Open (' . ($counts['open'] ?? 0) . ')',
                            'closed' => 'Closed (' . ($counts['closed'] ?? 0) . ')',
                        ];
                    })
                    ->default('open')
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! $value) {
                            return $query;
                        }

                        return $query->whereHas(
                            'thread',
                            fn(Builder $threadQuery) =>
                            $threadQuery->where('status', $value)
                        );
                    }),
                Tables\Filters\SelectFilter::make('read_status')
                    ->label('Read')
                    ->options([
                        'read' => 'Read',
                        'unread' => 'Unread',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'read' => $query->where('is_read', true),
                            'unread' => $query->where('is_read', false),
                            default => $query,
                        };
                    }),
                Tables\Filters\Filter::make('received_date')
                    ->schema([
                        Forms\Components\DatePicker::make('received_from')
                            ->label('Received from'),
                        Forms\Components\DatePicker::make('received_until')
                            ->label('Received until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['received_from'] ?? null,
                                fn(Builder $query, string $date): Builder =>
                                $query->whereDate('received_at', '>=', $date)
                            )
                            ->when(
                                $data['received_until'] ?? null,
                                fn(Builder $query, string $date): Builder =>
                                $query->whereDate('received_at', '<=', $date)
                            );
                    }),
            ])
            ->defaultSort('received_at', 'desc')
            ->recordUrl(
                fn(EmailMessage $record): string =>
                static::getUrl('view', ['record' => $record])
            )
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('mark_read')
                        ->label('Mark read')
                        ->icon('heroicon-o-envelope-open')
                        ->visible(fn(EmailMessage $record): bool => ! $record->is_read)
                        ->action(function (EmailMessage $record): void {
                            static::markConversationRead($record);

                            Notification::make()
                                ->title('Conversation marked read')
                                ->success()
                                ->send();
                        }),
                    Actions\Action::make('mark_unread')
                        ->label('Mark unread')
                        ->icon('heroicon-o-envelope')
                        ->visible(fn(EmailMessage $record): bool => $record->is_read)
                        ->action(function (EmailMessage $record): void {
                            static::markConversationUnread($record);

                            Notification::make()
                                ->title('Conversation marked unread')
                                ->success()
                                ->send();
                        }),
                    Actions\Action::make('close_conversation')
                        ->label('Close conversation')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->visible(
                            fn(EmailMessage $record): bool =>
                            $record->thread && $record->thread->status !== 'closed'
                        )
                        ->action(function (EmailMessage $record): void {
                            static::updateConversationStatus($record, 'closed');

                            Notification::make()
                                ->title('Conversation closed')
                                ->success()
                                ->send();
                        }),
                    Actions\Action::make('reopen_conversation')
                        ->label('Reopen conversation')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->visible(
                            fn(EmailMessage $record): bool =>
                            $record->thread?->status === 'closed'
                        )
                        ->action(function (EmailMessage $record): void {
                            static::updateConversationStatus($record, 'open');

                            Notification::make()
                                ->title('Conversation reopened')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('mark_read')
                        ->label('Mark read')
                        ->icon('heroicon-o-envelope-open')
                        ->action(function (Collection $records): void {
                            static::markConversationsRead($records);
                        }),
                    Actions\BulkAction::make('mark_unread')
                        ->label('Mark unread')
                        ->icon('heroicon-o-envelope')
                        ->action(function (Collection $records): void {
                            static::markConversationsUnread($records);
                        }),
                    Actions\BulkAction::make('close_conversations')
                        ->label('Close conversations')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->action(function (Collection $records): void {
                            static::updateConversationsStatus($records, 'closed');
                        }),
                    Actions\BulkAction::make('reopen_conversations')
                        ->label('Reopen conversations')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->action(function (Collection $records): void {
                            static::updateConversationsStatus($records, 'open');
                        }),
                ]),
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
