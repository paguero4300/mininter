<?php

namespace App\Filament\Resources\TransmissionResource\Pages;

use App\Filament\Resources\TransmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransmissions extends ListRecords
{
    protected static string $resource = TransmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
