<?php

namespace App\Filament\Resources\EmailMessageResource\Pages;

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
                ->action(function (): void {
                    $this->record->thread?->update(['status' => 'closed']);
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
                ->action(function (): void {
                    $this->record->thread?->update(['status' => 'open']);
                    $this->record->load('thread');

                    Notification::make()
                        ->title('Conversation reopened')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('reply')
                ->label('Reply')
                ->icon('heroicon-o-arrow-uturn-left')
                ->form([
                    Forms\Components\TextInput::make('to')
                        ->label('To')
                        ->default(
                            fn(): string => $this->getReplyTarget()->from_email
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
        $replyTarget = $this->getReplyTarget();

        $to = $replyTarget->from_email;
        $subject = $replyTarget->subject;

        /** @var MailSettings $mailSettings */
        $mailSettings = app(MailSettings::class);

        if (! $mailSettings->enabled()) {
            Notification::make()
                ->title('Mail is disabled')
                ->danger()
                ->send();

            return;
        }

        $senderName = $mailSettings->senderName();
        $senderEmail = $mailSettings->senderEmail();
        $replyTo = $mailSettings->replyTo();

        try {
            /** @var ResendClient $resend */
            $resend = app(ResendClient::class);

            $sendPayload = [
                'from' => "{$senderName} <{$senderEmail}>",
                'to' => [$to],
                'subject' => $subject,
                'text' => $body,
                'headers' => [
                    'In-Reply-To' => $replyTarget->message_id,
                    'References' => $replyTarget->message_id,
                ],
            ];

            if ($replyTo) {
                $sendPayload['reply_to'] = [$replyTo];
            }

            $sent = $resend->emails->send($sendPayload);

            EmailMessage::create([
                'email_thread_id' => $replyTarget->email_thread_id,
                'direction' => 'outbound',
                'source' => 'resend',
                'message_id' => $sent->id,
                'in_reply_to' => $replyTarget->message_id,
                'from_name' => $senderName,
                'from_email' => $senderEmail,
                'to_email' => $to,
                'subject' => $subject,
                'text_body' => $body,
                'html_body' => null,
                'attachments' => [],
                'metadata' => [
                    'resend_email_id' => $sent->id,
                ],
                'is_read' => true,
                'sent_at' => now(),
            ]);

            $this->record->thread?->update([
                'last_message_at' => now(),
            ]);

            Notification::make()
                ->title('Reply sent')
                ->success()
                ->send();
        } catch (Throwable $e) {
            \Log::error('Inbox reply failed', [
                'email_message_id' => $this->record->id,
                'thread_id' => $this->record->email_thread_id,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Reply could not be sent')
                ->body('Please try again.')
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
