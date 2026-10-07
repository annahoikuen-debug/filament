<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Services\MailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function __construct(
        private readonly MailService $mailService,
    ) {
    }

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
            'preferred_time' => ['required', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $booking = Booking::create(array_merge(
            $validator->validated(),
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
     * 予約一覧（営業担当用）
     */
    public function index()
    {
        $bookings = Booking::where('status', 'pending')
            ->orderBy('preferred_date')
            ->orderBy('preferred_time')
            ->get();

        return response()->json([
            'success' => true,
            'bookings' => $bookings,
        ]);
    }
}
