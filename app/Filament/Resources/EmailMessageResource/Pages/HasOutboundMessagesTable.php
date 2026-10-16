<?php

namespace App\Filament\Resources\EmailMessageResource\Pages;

use App\Filament\Resources\EmailMessageResource;
use App\Models\EmailMessage;
use App\Support\AdminAccess;
use Filament\Actions\Action;
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
                    ->wrap()
                    ->searchable(),
                TextColumn::make('subject')
                    ->label('Subject')
                    ->placeholder('(No subject)')
                    ->searchable()
                    ->limit(65),
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
                    ->label('Updated')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
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
            ->emptyStateHeading($this->isDraftList() ? 'No drafts' : 'No sent messages')
            ->paginated([10, 25, 50]);
    }
}
