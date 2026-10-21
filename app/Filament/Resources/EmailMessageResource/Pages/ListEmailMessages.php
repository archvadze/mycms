<?php

namespace App\Filament\Resources\EmailMessageResource\Pages;

use App\Filament\Resources\EmailMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailMessages extends ListRecords
{
    use HasEmailManagerNavigation;

    protected static string $resource = EmailMessageResource::class;

    protected function getHeaderActions(): array
    {
        return $this->emailNavigationActions('index');
    }
}
