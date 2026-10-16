<?php

namespace App\Filament\Resources\EmailMessageResource\Pages;

use App\Filament\Resources\EmailMessageResource;
use App\Support\AdminAccess;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class DraftEmailMessages extends Page implements HasTable
{
    use HasOutboundMessagesTable, InteractsWithTable {
        HasOutboundMessagesTable::table insteadof InteractsWithTable;
    }

    protected static string $resource = EmailMessageResource::class;
    protected string $view = 'filament.resources.email-message-resource.pages.outbound-list';

    public static function canAccess(array $parameters = []): bool
    {
        return AdminAccess::canComposeEmail();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }


    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('inbox')
                ->label('Inbox')
                ->icon('heroicon-o-inbox')
                ->color('gray')
                ->url(EmailMessageResource::getUrl('index')),

            \Filament\Actions\Action::make('compose')
                ->label('Compose')
                ->icon('heroicon-o-pencil-square')
                ->url(EmailMessageResource::getUrl('compose')),

            \Filament\Actions\Action::make('sent')
                ->label('Sent')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->url(EmailMessageResource::getUrl('sent')),

            \Filament\Actions\Action::make('drafts')
                ->label('Drafts')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(EmailMessageResource::getUrl('drafts')),
        ];
    }

    protected function isDraftList(): bool
    {
        return true;
    }
}
