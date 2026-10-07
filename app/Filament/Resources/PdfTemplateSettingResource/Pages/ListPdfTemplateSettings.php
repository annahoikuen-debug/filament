<?php

namespace App\Filament\Resources\PdfTemplateSettingResource\Pages;

use App\Filament\Resources\PdfTemplateSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPdfTemplateSettings extends ListRecords
{
    protected static string $resource = PdfTemplateSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
