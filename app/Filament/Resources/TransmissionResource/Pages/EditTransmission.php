<?php

namespace App\Filament\Resources\TransmissionResource\Pages;

use App\Filament\Resources\TransmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransmission extends EditRecord
{
    protected static string $resource = TransmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
