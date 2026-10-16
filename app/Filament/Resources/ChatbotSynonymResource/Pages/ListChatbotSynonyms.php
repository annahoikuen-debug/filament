<?php

namespace App\Filament\Resources\ChatbotSynonymResource\Pages;

use App\Filament\Resources\ChatbotSynonymResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChatbotSynonyms extends ListRecords
{
    protected static string $resource = ChatbotSynonymResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
