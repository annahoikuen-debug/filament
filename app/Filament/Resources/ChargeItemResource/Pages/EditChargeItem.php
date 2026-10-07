<?php

namespace App\Filament\Resources\ChargeItemResource\Pages;

use App\Filament\Resources\ChargeItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChargeItem extends EditRecord
{
    protected static string $resource = ChargeItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
