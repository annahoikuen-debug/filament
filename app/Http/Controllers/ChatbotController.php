<?php

namespace App\Http\Controllers;

use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\DTO\ChatRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly ChatbotService $chatbotService,
    ) {}

    public function message(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user === null || $user->is_admin !== true) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:500'],
            'session_id' => ['nullable', 'string', 'max:100'],
        ]);

        $chatRequest = ChatRequest::from($validated);

        $response = $this->chatbotService->handle($chatRequest, $user);

        return response()->json($response->toArray());
    }
}
