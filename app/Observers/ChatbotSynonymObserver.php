<?php

namespace App\Observers;

use App\Models\ChatbotSynonym;
use App\Services\Chatbot\SynonymExpander;

class ChatbotSynonymObserver
{
    public function saved(ChatbotSynonym $synonym): void
    {
        SynonymExpander::flushCache();
    }

    public function deleted(ChatbotSynonym $synonym): void
    {
        SynonymExpander::flushCache();
    }

    public function restored(ChatbotSynonym $synonym): void
    {
        SynonymExpander::flushCache();
    }
}
