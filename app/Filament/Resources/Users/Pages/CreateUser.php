<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\AdminAudit;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        AdminAudit::logRoleChange(
            $this->record,
            [],
            $this->record->roles()->pluck('name')->all()
        );
    }
}
