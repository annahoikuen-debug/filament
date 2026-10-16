<?php

namespace App\Filament\Resources\ChatbotFaqSuggestionResource\Pages;

use App\Filament\Resources\ChatbotFaqSuggestionResource;
use Filament\Resources\Pages\ListRecords;

class ListChatbotFaqSuggestions extends ListRecords
{
    protected static string $resource = ChatbotFaqSuggestionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
