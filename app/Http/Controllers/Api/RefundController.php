<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Payments\PaymentService;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {
    }

    public function refund(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'amount' => 'nullable|numeric|min:1',
        ]);

        $order = \App\Models\Order::with('user')->findOrFail($data['order_id']);
        
        // البحث عن آخر عملية دفع ناجحة لهذا الطلب
        $payment = \App\Models\Payment::where('order_id', $order->id)
            ->where('status', \App\Enum\PaymentStatus::PAID)
            ->latest()
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على عملية دفع ناجحة لهذا الطلب'
            ], 422);
        }

        $refundAmount = $data['amount'] ?? $payment->amount;

        // تنفيذ عملية الاسترجاع عبر بوابة الدفع
        $success = $this->paymentService->refund(
            $payment->payment_intent_id, 
            $refundAmount, 
            $payment->provider->value
        );

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية استرجاع الأموال من خلال بوابة الدفع'
            ], 422);
        }

        // تحديث حالة الطلب
        $order->update(['status' => \App\Enum\OrderStatus::REFUNDED]);

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع الأموال بنجاح وتحديث حالة الطلب'
        ]);
    }
}
