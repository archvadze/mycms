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
                'headers' => [
                    'In-Reply-To' => $this->record->message_id,
                    'References' => $this->record->message_id,
                ],
            ]);

            $sentEmail = $resend->emails->get($sent->id);

            $sentMessageId = $sentEmail->message_id ?? $sent->id;

            EmailMessage::create([
                'email_thread_id' => $this->record->email_thread_id,
                'direction' => 'outbound',
                'source' => 'resend',
                'message_id' => $sentMessageId,
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
            $configHasKey = is_string(config('resend.api_key'))
                && config('resend.api_key') !== '';

            $envHasKey = is_string(getenv('RESEND_API_KEY'))
                && getenv('RESEND_API_KEY') !== '';

            $cachedConfigPath = app()->getCachedConfigPath();

            $cachedConfigHasKey = false;

            if (is_file($cachedConfigPath)) {
                $cached = require $cachedConfigPath;

                $cachedConfigHasKey = ! empty($cached['resend']['api_key']);
            }

            \Log::error('Inbox reply failed', [
                'email_message_id' => $this->record->id,
                'thread_id' => $this->record->email_thread_id,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'resend_config_key_present' => $configHasKey,
                'process_env_key_present' => $envHasKey,
                'cached_config_key_present' => $cachedConfigHasKey,
                'cached_config_path' => $cachedConfigPath,
            ]);

            Notification::make()
                ->title('Reply could not be sent')
                ->body(
                    $e->getMessage()
                        . ' | config=' . ($configHasKey ? 'yes' : 'no')
                        . ' | env=' . ($envHasKey ? 'yes' : 'no')
                        . ' | cache=' . ($cachedConfigHasKey ? 'yes' : 'no')
                )
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
