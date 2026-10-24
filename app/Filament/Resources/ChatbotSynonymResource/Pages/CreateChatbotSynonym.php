<?php

namespace App\Filament\Resources\ChatbotSynonymResource\Pages;

use App\Filament\Resources\ChatbotSynonymResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChatbotSynonym extends CreateRecord
{
    protected static string $resource = ChatbotSynonymResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        if ($user && $user->requiresFacilityScope() && empty($data['facility_id'])) {
            $data['facility_id'] = $user->facility_id;
        }

        return $data;
    }
}
