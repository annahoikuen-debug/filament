<?php

namespace App\Http\Controllers;

use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Services\MailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    public function __construct(
        private readonly MailService $mailService,
    ) {}

    /**
     * デモ面談の予約を受付
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trial_id' => ['nullable', 'exists:trials,id'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'preferred_date' => ['required', 'date', 'after_or_equal:today'],
            'preferred_time' => ['required', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // 二重予約防止（同一メールアドレス・日時）
        // preferred_date は date キャストにより 'Y-m-d H:i:s' で保存されるため、
        // whereDate で日付部分のみ比較する
        $duplicate = Booking::where('email', $validated['email'])
            ->whereDate('preferred_date', $validated['preferred_date'])
            ->where('preferred_time', $validated['preferred_time'])
            ->whereNull('deleted_at')
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => '同じ日時の予約が既に存在します。',
            ], 409);
        }

        $booking = Booking::create(array_merge(
            $validated,
            ['status' => 'pending']
        ));

        // 予約確認メール送信
        $this->mailService->send(
            new BookingConfirmationMail($booking),
            $booking->email,
        );

        return response()->json([
            'success' => true,
            'message' => 'デモ面談の予約を受け付けました。確認メールをお送りします。',
            'booking_id' => $booking->id,
            'preferred_date' => $booking->preferred_date,
            'preferred_time' => $booking->preferred_time,
        ], 201);
    }

    /**
     * 予約一覧（営業担当用・管理者のみ）
     */
    public function index()
    {
        $user = auth()->user();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => '認証が必要です。',
            ], 401);
        }

        if (! $user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => '管理者権限が必要です。',
            ], 403);
        }

        $bookings = Booking::where('status', 'pending')
            ->orderBy('preferred_date')
            ->orderBy('preferred_time')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'bookings' => $bookings,
        ]);
    }
}
