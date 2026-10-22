<?php

namespace App\Filament\Resources\EmailMessageResource\Pages;

use App\Support\AdminAccess;
use App\Filament\Resources\EmailMessageResource;
use App\Models\EmailMessage;
use App\Services\MailSettings;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Resend\Contracts\Client as ResendClient;
use Throwable;

class ViewEmailMessage extends ViewRecord
{
    protected static string $resource = EmailMessageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        EmailMessageResource::markConversationRead($this->record);
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('close_conversation')
                ->label('Close Conversation')
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->visible(
                    fn(): bool => $this->record->thread
                        && $this->record->thread->status !== 'closed'
                )
                ->authorize(fn(): bool => EmailMessageResource::canEdit($this->record))
                ->action(function (): void {
                    EmailMessageResource::updateConversationStatus($this->record, 'closed');
                    $this->record->load('thread');

                    Notification::make()
                        ->title('Conversation closed')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('reopen_conversation')
                ->label('Reopen Conversation')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(
                    fn(): bool => $this->record->thread?->status === 'closed'
                )
                ->authorize(fn(): bool => EmailMessageResource::canEdit($this->record))
                ->action(function (): void {
                    EmailMessageResource::updateConversationStatus($this->record, 'open');
                    $this->record->load('thread');

                    Notification::make()
                        ->title('Conversation reopened')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('forward')
                ->label('Forward')
                ->icon('heroicon-o-arrow-uturn-right')
                ->authorize(fn (): bool => AdminAccess::canComposeEmail())
                ->url(fn (): string => EmailMessageResource::getUrl('compose', ['forward' => $this->record->getKey()])),
            Actions\Action::make('reply')
                ->authorize(fn (): bool => AdminAccess::canComposeEmail())
                ->label('Reply')
                ->icon('heroicon-o-arrow-uturn-left')
                ->form([
                    Forms\Components\TextInput::make('to')
                        ->label('To')
                        ->default(
                            fn(): string => $this->getReplyTarget()->replyRecipient()
                        )
                        ->disabled(),

                    Forms\Components\TextInput::make('subject')
                        ->label('Subject')
                        ->default(
                            fn(): string => $this->replySubject(
                                $this->getReplyTarget()->subject
                            )
                        )
                        ->disabled(),

                    Forms\Components\Textarea::make('body')
                        ->label('Message')
                        ->required()
                        ->rows(12)
                        ->columnSpanFull(),
                ])
                ->modalHeading('Reply to email')
                ->modalSubmitActionLabel('Send')
                ->action(function (array $data): void {
                    $this->sendReply($data['body']);
                }),
        ];
    }

    private function getReplyTarget(): EmailMessage
    {
        return $this->record->thread
            ?->messages()
            ->where('direction', 'inbound')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->first()
            ?? $this->record;
    }

    private function sendReply(string $body): void
    {
        try {
            app(\App\Services\Mail\EmailSendingService::class)->sendReply($this->getReplyTarget(), $body);

            Notification::make()
                ->title('Reply sent')
                ->success()
                ->send();
        } catch (Throwable $e) {
            \Log::error('Inbox reply failed', [
                'email_message_id' => $this->record->id,
                'exception' => get_class($e),
            ]);

            Notification::make()
                ->title('Reply could not be confirmed')
                ->body('Check Sent status before retrying. The provider may already have accepted this reply.')
                ->danger()
                ->send();
        }
    }

    private function replySubject(?string $subject): string
    {
        $subject = trim($subject ?: '(No subject)');

        if (preg_match('/^re:/i', $subject)) {
            return $subject;
        }

        return 'Re: ' . $subject;
    }
}
