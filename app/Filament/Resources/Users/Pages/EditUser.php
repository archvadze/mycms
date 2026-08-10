<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\AdminAudit;
use App\Support\AdminAccess;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private array $oldRoles = [];

    protected function beforeSave(): void
    {
        $this->oldRoles = $this->record->roles()->pluck('name')->all();
    }

    protected function afterSave(): void
    {
        $newRoles = $this->record->refresh()->roles()->pluck('name')->all();

        if (! AdminAccess::canChangeRoles($this->record, $newRoles)) {
            $this->record->syncRoles($this->oldRoles);

            throw ValidationException::withMessages([
                'data.roles' => 'You cannot remove your own Super Admin role.',
            ]);
        }

        AdminAudit::logRoleChange($this->record, $this->oldRoles, $newRoles);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn(): bool => UserResource::canDelete($this->record)),
        ];
    }
}
