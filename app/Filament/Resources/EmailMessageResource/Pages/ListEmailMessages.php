<?php

namespace App\Filament\Resources\EmailMessageResource\Pages;

use App\Filament\Resources\EmailMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailMessages extends ListRecords
{
    protected static string $resource = EmailMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('compose')
                ->label('Compose')
                ->icon('heroicon-o-pencil-square')
                ->url(EmailMessageResource::getUrl('compose'))
                ->visible(fn (): bool => \App\Filament\Resources\EmailMessageResource\Pages\ComposeEmail::canAccess()),
            \Filament\Actions\Action::make('sent')->label('Sent')->icon('heroicon-o-paper-airplane')->url(EmailMessageResource::getUrl('sent'))->visible(fn (): bool => \App\Support\AdminAccess::canComposeEmail()),
            \Filament\Actions\Action::make('drafts')->label('Drafts')->icon('heroicon-o-document-text')->url(EmailMessageResource::getUrl('drafts'))->visible(fn (): bool => \App\Support\AdminAccess::canComposeEmail()),
        ];
    }
}
