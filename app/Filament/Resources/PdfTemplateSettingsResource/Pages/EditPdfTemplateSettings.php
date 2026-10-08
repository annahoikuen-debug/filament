<?php

namespace App\Filament\Resources\PdfTemplateSettingsResource\Pages;

use App\Filament\Resources\PdfTemplateSettingsResource;
use Filament\Resources\Pages\EditRecord;

class EditPdfTemplateSettings extends EditRecord
{
    protected static string $resource = PdfTemplateSettingsResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'テンプレートを更新しました（バージョンがインクリメントされました）';
    }
}