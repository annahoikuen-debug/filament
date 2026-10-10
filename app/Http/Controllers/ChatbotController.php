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

    public function feedback(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user === null || $user->is_admin !== true) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'chat_log_id' => ['required', 'integer'],
            'feedback' => ['required', 'string', 'in:helpful,unhelpful'],
        ]);

        $log = \App\Models\ChatLog::query()
            ->where('id', $validated['chat_log_id'])
            ->when($user->facility_id !== null, fn ($q) => $q->where('facility_id', $user->facility_id))
            ->first();

        if ($log === null) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $log->update([
            'feedback' => $validated['feedback'],
            'feedback_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'feedback' => $validated['feedback']]);
    }
}
