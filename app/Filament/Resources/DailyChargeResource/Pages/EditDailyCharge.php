<?php

namespace App\Filament\Resources\DailyChargeResource\Pages;

use App\Filament\Resources\DailyChargeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDailyCharge extends EditRecord
{
    protected static string $resource = DailyChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
