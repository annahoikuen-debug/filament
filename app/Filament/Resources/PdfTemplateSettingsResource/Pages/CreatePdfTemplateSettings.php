<?php

namespace App\Filament\Resources\PdfTemplateSettingsResource\Pages;

use App\Filament\Resources\PdfTemplateSettingsResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePdfTemplateSettings extends CreateRecord
{
    protected static string $resource = PdfTemplateSettingsResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'テンプレートを作成しました';
    }
}