<?php

namespace App\Filament\Resources\PdfTemplateSettingResource\Pages;

use App\Filament\Resources\PdfTemplateSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePdfTemplateSetting extends CreateRecord
{
    protected static string $resource = PdfTemplateSettingResource::class;
}
