<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\Project;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...array_map(
                fn($status): Actions\Action => ProjectResource::statusAction($status),
                \App\Enums\ProjectStatus::cases()
            ),
            Actions\EditAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn(): bool => $this->record instanceof Project && ProjectResource::canDelete($this->record)),
        ];
    }
}
