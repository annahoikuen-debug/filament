<?php

namespace App\Filament\Resources\TaxSettingResource\Pages;

use App\Filament\Resources\TaxSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaxSettings extends ListRecords
{
    protected static string $resource = TaxSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
