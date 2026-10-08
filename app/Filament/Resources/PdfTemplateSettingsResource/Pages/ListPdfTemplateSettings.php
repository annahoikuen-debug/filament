<?php

namespace App\Filament\Resources\PdfTemplateSettingsResource\Pages;

use App\Filament\Resources\PdfTemplateSettingsResource;
use Filament\Resources\Pages\ListRecords;

class ListPdfTemplateSettings extends ListRecords
{
    protected static string $resource = PdfTemplateSettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->label('新規テンプレート作成')
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}