<?php

namespace App\Filament\Resources\EmailMessageResource\Pages;

use App\Filament\Resources\EmailMessageResource;
use App\Support\AdminAccess;
use Filament\Actions\Action;

trait HasEmailManagerNavigation
{
    /** @return array<Action> */
    protected function emailNavigationActions(string $current): array
    {
        $actions = [];

        if ($current !== 'index') {
            $actions[] = Action::make('inbox')
                ->label('Inbox')
                ->icon('heroicon-o-inbox')
                ->color('gray')
                ->url(EmailMessageResource::getUrl('index'));
        }

        if (AdminAccess::canComposeEmail()) {
            $actions[] = Action::make('compose')
                ->label('Compose')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url(EmailMessageResource::getUrl('compose'));

            if ($current !== 'sent') {
                $actions[] = Action::make('sent')
                    ->label('Sent')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('gray')
                    ->url(EmailMessageResource::getUrl('sent'));
            }

            if ($current !== 'drafts') {
                $actions[] = Action::make('drafts')
                    ->label('Drafts')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(EmailMessageResource::getUrl('drafts'));
            }
        }

        return $actions;
    }
}
