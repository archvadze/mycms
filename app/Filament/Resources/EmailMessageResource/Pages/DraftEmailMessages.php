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

    use HasEmailManagerNavigation;

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
        return $this->emailNavigationActions('drafts');
    }

    protected function isDraftList(): bool
    {
        return true;
    }
}
