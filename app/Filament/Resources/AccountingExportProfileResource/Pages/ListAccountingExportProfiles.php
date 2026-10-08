<?php

namespace App\Filament\Resources\AccountingExportProfileResource\Pages;

use App\Filament\Resources\AccountingExportProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccountingExportProfiles extends ListRecords
{
    protected static string $resource = AccountingExportProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
