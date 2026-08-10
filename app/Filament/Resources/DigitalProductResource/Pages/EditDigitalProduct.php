<?php

namespace App\Filament\Resources\DigitalProductResource\Pages;

use App\Filament\Resources\DigitalProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDigitalProduct extends EditRecord
{
    protected static string $resource = DigitalProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->authorize(fn(): bool => DigitalProductResource::canDelete($this->record))
                ->visible(fn(): bool => DigitalProductResource::canDelete($this->record)),
        ];
    }
}
