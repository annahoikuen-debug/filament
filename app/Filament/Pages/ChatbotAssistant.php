<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ChatbotAssistant extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'チャットボット';

    protected static ?string $title = 'チャットボット';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.chatbot-assistant';
}
