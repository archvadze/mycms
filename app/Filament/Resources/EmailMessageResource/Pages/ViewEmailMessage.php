<?php

namespace App\Filament\Resources\EmailMessageResource\Pages;

use App\Filament\Resources\EmailMessageResource;
use App\Models\EmailMessage;
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

        if (! $this->record->is_read) {
            $this->record->update([
                'is_read' => true,
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reply')
                ->label('Reply')
                ->icon('heroicon-o-arrow-uturn-left')
                ->form([
                    Forms\Components\TextInput::make('to')
                        ->label('To')
                        ->default(fn(): string => $this->record->from_email)
                        ->disabled(),

                    Forms\Components\TextInput::make('subject')
                        ->label('Subject')
                        ->default(
                            fn(): string => $this->replySubject(
                                $this->record->subject
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

    private function sendReply(string $body): void
    {
        $to = $this->record->from_email;
        $subject = $this->replySubject($this->record->subject);

        try {
            /** @var ResendClient $resend */
            $resend = app(ResendClient::class);

            $sent = $resend->emails->send([
                'from' => 'Archvadze <admin@archvadze.com>',
                'to' => [$to],
                'subject' => $subject,
                'text' => $body,
            ]);

            EmailMessage::create([
                'email_thread_id' => $this->record->email_thread_id,
                'direction' => 'outbound',
                'source' => 'resend',
                'message_id' => $sent->id,
                'in_reply_to' => $this->record->message_id,
                'from_name' => 'Archvadze',
                'from_email' => 'admin@archvadze.com',
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
                ->body($e->getMessage())
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
