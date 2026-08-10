<?php

namespace App\Filament\Resources\DigitalProductVersionResource\Pages;

use App\Filament\Resources\DigitalProductVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDigitalProductVersion extends EditRecord
{
    protected static string $resource = DigitalProductVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->authorize(fn(): bool => DigitalProductVersionResource::canDelete($this->record))
                ->visible(fn(): bool => DigitalProductVersionResource::canDelete($this->record)),
        ];
    }
}
