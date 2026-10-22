<?php
namespace App\Filament\Resources\EmailMessageResource\Pages;

use App\Filament\Resources\EmailMessageResource;
use App\Models\EmailMessage;
use App\Services\Mail\EmailSendingService;
use App\Services\Mail\ForwardEmailPrefill;
use App\Services\MailSettings;
use App\Support\AdminAccess;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use App\Services\Mail\MailboxIdentity;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ComposeEmail extends Page
{
    protected static string $resource = EmailMessageResource::class;
    protected string $view = 'filament.resources.email-message-resource.pages.compose-email';
    protected string|Width|null $maxContentWidth = Width::Full;
    public ?array $data = [];
    public ?int $draftId = null;

    public static function canAccess(array $parameters = []): bool { return AdminAccess::canComposeEmail(); }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $id = request()->query('draft');
        if ($id !== null) {
            abort_unless(ctype_digit((string) $id), 404);
            $draft = EmailMessage::query()->where('direction', 'outbound')->where('status', 'draft')->findOrFail((int)$id);
            $this->draftId = $draft->id;
            $this->form->fill([
                'from' => $draft->from_email ?: app(MailSettings::class)->senderEmail(),
                'to' => $draft->recipients ?? [], 'cc' => $draft->cc_recipients ?? [], 'bcc' => $draft->bcc_recipients ?? [],
                'subject' => $draft->subject, 'body' => $draft->html_body ?: e($draft->text_body),
                'attachments' => collect($draft->attachments ?? [])->pluck('path')->all(),
            ]);
            return;
        }
        $forwardId = request()->query('forward');
        if ($forwardId !== null) {
            abort_unless(ctype_digit((string) $forwardId) && (int) $forwardId > 0, 404);
            $original = EmailMessage::query()
                ->whereIn('direction', ['inbound', 'outbound'])
                ->findOrFail((int) $forwardId);
            $prefill = ForwardEmailPrefill::fromMessage($original);
            $this->form->fill([
                'from' => app(MailSettings::class)->senderEmail(),
                'to' => [], 'cc' => [], 'bcc' => [],
                'subject' => $prefill['subject'],
                'body' => $prefill['body'],
                'attachments' => [],
            ]);
            return;
        }
        $this->form->fill(['from' => app(MailSettings::class)->senderEmail(), 'to' => [], 'cc' => [], 'bcc' => [], 'attachments' => []]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('New message')->schema([
                Select::make('from')->label('From')->options(fn (): array => MailboxIdentity::composeOptions(app(MailSettings::class)))->required(),
                TagsInput::make('to')->label('To')->placeholder('Enter address and press Enter')->required(),
                TagsInput::make('cc')->label('CC'),
                TagsInput::make('bcc')->label('BCC'),
                TextInput::make('subject')->maxLength(255),
                RichEditor::make('body')->label('Message')->columnSpanFull(),
                FileUpload::make('attachments')->multiple()->disk('local')->directory('email-outgoing')
                    ->visibility('private')->maxFiles(10)->maxSize(10240)
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'])
                    ->helperText('Max 10 MB per file; 20 MB total. Files are stored privately.')
                    ->columnSpanFull(),
            ])->columns(1),
        ])->statePath('data');
    }

    private function payload(bool $sending): array
    {
        $state = $this->form->getState();
        $paths = array_values($state['attachments'] ?? []);
        $files = [];
        foreach ($paths as $path) {
            if (! is_string($path) || !str_starts_with($path, 'email-outgoing/') || !Storage::disk('local')->exists($path)) {
                throw new RuntimeException('Missing or invalid attachment.');
            }
            $files[] = [
                'path' => $path, 'filename' => basename($path),
                'content_type' => Storage::disk('local')->mimeType($path) ?: '',
                'size' => Storage::disk('local')->size($path),
            ];
        }
        return [
            (array) ($state['to'] ?? []), (array) ($state['cc'] ?? []), (array) ($state['bcc'] ?? []),
            (string) ($state['subject'] ?? ''), (string) ($state['body'] ?? ''), $files,
            $this->draftId ? EmailMessage::query()->where('status', 'draft')->where('direction', 'outbound')->findOrFail($this->draftId) : null,
            (string) ($state['from'] ?? ''),
        ];
    }

    public function saveDraft(EmailSendingService $sender): void
    {
        abort_unless(static::canAccess(), 403);
        try {
            $draft = $sender->saveDraft(...$this->payload(false));
            $this->draftId = $draft->id;
            Notification::make()->title('Draft saved')->success()->send();
        } catch (Throwable $e) {
            Log::error('Draft save failed', ['exception' => get_class($e)]);
            Notification::make()->title('Draft could not be saved')->body($e->getMessage())->danger()->send();
        }
    }

    public function sendEmail(EmailSendingService $sender): void
    {
        abort_unless(static::canAccess(), 403);
        try {
            $sender->sendMessage(...$this->payload(true));
            Notification::make()->title('Email sent')->success()->send();
            $this->redirect(EmailMessageResource::getUrl('sent'));
        } catch (Throwable $e) {
            Log::error('Compose email failed', ['exception' => get_class($e)]);
            Notification::make()->title('Email could not be confirmed')->body('Check Sent status before retrying. The provider may already have accepted the message.')->danger()->send();
        }
    }
}
