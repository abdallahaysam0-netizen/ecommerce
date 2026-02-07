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
        $this->middleware('auth:sanctum');
    }

    public function refund(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|integer',
            'amount' => 'required|numeric|min:1',
        ]);

        $order = \App\Models\Order::findOrFail($data['order_id']);

        $success = $this->paymentService->refund($order, $data['amount']);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Refund failed'
            ], 422);
        }

        $order->update(['status' => 'refunded']);

        return response()->json([
            'success' => true,
            'message' => 'Refund processed successfully'
        ]);
    }
}
