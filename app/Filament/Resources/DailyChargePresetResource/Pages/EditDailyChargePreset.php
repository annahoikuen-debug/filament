<?php

namespace App\Filament\Resources\DailyChargePresetResource\Pages;

use App\Filament\Resources\DailyChargePresetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDailyChargePreset extends EditRecord
{
    protected static string $resource = DailyChargePresetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
