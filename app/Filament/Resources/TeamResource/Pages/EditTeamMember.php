<?php
namespace App\Filament\Resources\TeamResource\Pages;

use App\Filament\Resources\TeamResource;
use App\Support\AdminAccess;
use App\Support\AdminAudit;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditTeamMember extends EditRecord
{
    protected static string $resource = TeamResource::class;

    private array $oldRoles = [];

    private ?string $pendingRole = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->record->roles()->value('name');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingRole = $data['roles'] ?? null;
        unset($data['roles']);

        $newRoles = $this->pendingRole ? [$this->pendingRole] : [];

        if (! AdminAccess::canChangeRoles($this->record, $newRoles)) {
            throw ValidationException::withMessages([
                'data.roles' => 'You cannot remove your own Super Admin role.',
            ]);
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        $this->oldRoles = $this->record->roles()->pluck('name')->all();
    }

    protected function afterSave(): void
    {
        if (! $this->pendingRole) {
            return;
        }

        $this->record->syncRoles([$this->pendingRole]);

        AdminAudit::logRoleChange(
            $this->record,
            $this->oldRoles,
            $this->record->roles()->pluck('name')->all()
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->authorize(fn(): bool => TeamResource::canDelete($this->record))
                ->visible(fn(): bool => TeamResource::canDelete($this->record)),
        ];
    }
}
