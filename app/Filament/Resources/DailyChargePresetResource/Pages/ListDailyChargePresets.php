<?php

namespace App\Filament\Resources\DailyChargePresetResource\Pages;

use App\Filament\Resources\DailyChargePresetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDailyChargePresets extends ListRecords
{
    protected static string $resource = DailyChargePresetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
