<?php

namespace App\Http\Controllers\Api;

use App\Enum\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Payments\PaymentService;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function pay(Request $request, $sessionId)
    {
        $paymentSession = Payment::findOrFail($sessionId);

        // التحقق من أن الدفع لم يتم بالفعل
        if ($paymentSession->status === PaymentStatus::PAID) {
            return response()->json([
                'success' => false,
                'message' => 'Payment already completed'
            ], 400);
        }

        // استلام نوع الدفع من الـ Frontend (مثل: fawry, card, wallet)
        $method = $request->input('method', 'card');

        // تمرير النوع للخدمة لكي تختار الـ Integration ID الصحيح
        $result = $this->paymentService->pay($paymentSession, $method);

        if (!$result->success) {
            $paymentSession->markAsFailed($result->data ?? []);
            return response()->json([
                'success' => false,
                'message' => $result->message
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result->data
        ]);
    }
}