<?php

namespace App\Filament\Resources\EmailMessageResource\Pages;

use App\Filament\Resources\EmailMessageResource;
use App\Models\EmailMessage;
use App\Services\Mail\MailboxIdentity;
use App\Services\MailSettings;
use Filament\Tables\Filters\SelectFilter;
use App\Support\AdminAccess;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

trait HasOutboundMessagesTable
{
    abstract protected function isDraftList(): bool;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => EmailMessage::query()
                ->where('direction', 'outbound')
                ->when(
                    $this->isDraftList(),
                    fn (Builder $query) => $query->where('status', 'draft'),
                    fn (Builder $query) => $query->where('status', '!=', 'draft'),
                ))
            ->columns([
                TextColumn::make('to_email')
                    ->label('To')
                    ->formatStateUsing(fn (EmailMessage $record): string => implode(', ', $record->recipients ?: array_filter([$record->to_email])))
                    ->limit(28)
                    ->tooltip(fn (EmailMessage $record): string =>
                        implode(', ', $record->recipients ?: array_filter([$record->to_email]))
                    )
                    ->searchable(query: fn (Builder $query, string $search): Builder =>
                        $query->where(function (Builder $q) use ($search): void {
                            $q->where('to_email', 'like', "%{$search}%")
                                ->orWhere('recipients', 'like', "%{$search}%");
                        })
                    ),
                TextColumn::make('subject')
                    ->label('Subject')
                    ->placeholder('(No subject)')
                    ->searchable()
                    ->limit(42)
                    ->tooltip(fn (EmailMessage $record): string => $record->subject ?? ''),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'draft' => 'gray',
                        'unknown', 'failed' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?: 'unknown')),
                TextColumn::make('updated_at')
                    ->label($this->isDraftList() ? 'Updated' : 'Activity')
                    ->state(fn (EmailMessage $record) =>
                        $this->isDraftList()
                            ? ($record->draft_saved_at ?? $record->updated_at)
                            : ($record->sent_at ?? $record->updated_at)
                    )
                    ->since()
                    ->sortable(query: fn (Builder $query, string $direction): Builder =>
                        $query->orderByRaw(
                            'COALESCE('
                            . ($this->isDraftList() ? 'draft_saved_at' : 'sent_at')
                            . ', updated_at) '
                            . ($direction === 'asc' ? 'ASC' : 'DESC')
                        )
                    )
                    ->tooltip(fn (EmailMessage $record): ?string =>
                        ($this->isDraftList()
                            ? ($record->draft_saved_at ?? $record->updated_at)
                            : ($record->sent_at ?? $record->updated_at)
                        )?->format('Y-m-d H:i:s')
                    ),
            ])
            ->filters([
                SelectFilter::make('mailbox')
                    ->label('Mailbox')
                    ->options(fn (): array => MailboxIdentity::composeOptions(app(MailSettings::class)))
                    ->query(function (Builder $query, array $data): Builder {
                        $mailbox = $data['value'] ?? null;
                        return $mailbox ? $query->where('from_email', $mailbox) : $query;
                    }),
                ...($this->isDraftList() ? [] : [
                    SelectFilter::make('status')
                    ->label('Delivery status')
                    ->options([
                        'sent' => 'Sent',
                        'unknown' => 'Unknown',
                        'failed' => 'Failed',
                        'pending' => 'Pending',
                        'sending' => 'Sending',
                    ]),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (EmailMessage $record): string => $this->isDraftList()
                ? EmailMessageResource::getUrl('compose', ['draft' => $record->getKey()])
                : EmailMessageResource::getUrl('sent-view', ['record' => $record->getKey()]))
            ->recordActions([
                ActionGroup::make([
                    Action::make('viewMessage')
                        ->label('View message')
                        ->icon('heroicon-o-eye')
                        ->url(fn (EmailMessage $record): string => EmailMessageResource::getUrl('sent-view', ['record' => $record->getKey()]))
                        ->visible(fn (EmailMessage $record): bool => $record->status !== 'draft' && AdminAccess::canComposeEmail()),
                Action::make('editDraft')
                    ->label('Edit draft')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (EmailMessage $record): string => EmailMessageResource::getUrl('compose', ['draft' => $record->id]))
                    ->visible(fn (EmailMessage $record): bool => $record->status === 'draft' && AdminAccess::canComposeEmail()),
                Action::make('deleteMessage')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete email record?')
                    ->modalDescription('This deletes the message from the manager. A sent email cannot be recalled. Stored attachment files are not deleted by this action.')
                    ->visible(fn (EmailMessage $record): bool => AdminAccess::canComposeEmail()
                        && $record->direction === 'outbound'
                        && ! in_array($record->status, ['pending', 'sending'], true))
                    ->action(function (EmailMessage $record): void {
                        abort_unless(AdminAccess::canComposeEmail(), 403);
                        // Recheck within the action in case status changed after rendering.
                        $deleted = EmailMessage::query()
                            ->whereKey($record->getKey())
                            ->where('direction', 'outbound')
                            ->whereNotIn('status', ['pending', 'sending'])
                            ->delete();
                        if (! $deleted) {
                            Notification::make()->title('Message can no longer be deleted')->warning()->send();
                            return;
                        }
                        Notification::make()->title('Email record deleted')->success()->send();
                    }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton(),
            ])
            ->emptyStateHeading($this->isDraftList() ? 'No drafts' : 'No sent messages')
            ->paginated([10, 25, 50]);
    }
}
