<?php

namespace App\Filament\Resources\ChargeItemResource\Pages;

use App\Filament\Resources\ChargeItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChargeItems extends ListRecords
{
    protected static string $resource = ChargeItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
