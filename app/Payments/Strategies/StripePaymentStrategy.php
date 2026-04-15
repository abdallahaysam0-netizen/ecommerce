<?php

namespace App\Payments\Strategies;

use App\Models\Order;
use App\Models\User;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Inventory\StockManager;

use App\Models\Cart;
use App\Models\Payment;
use App\Enum\PaymentProvider;
use App\Payments\PaymentService;
use Illuminate\Support\Facades\DB;

class StripePaymentStrategy implements OrderUpdateStrategy
{
    public function checkout(User $user, array $data): mixed
    {
        return DB::transaction(function () use ($user, $data) {
            // 1. تسجيل "محاولة الدفع" فقط بدون إنشاء طلب (كما طلب العميل للمنصات الأونلاين)
            $payment = Payment::create([
                'order_id' => null, 
                'user_id'  => $user->id,
                'provider' => PaymentProvider::STRIPE,
                'amount'   => $data['total'],
                'currency' => 'EGP',
                'status'   => PaymentStatus::INITIATED,
                'metadata' => [
                    'checkout_data' => [
                        'shipping' => $data['shipping'],
                        'items'    => $data['items_snapshot'], // لقطة للمنتجات لاستكمال الطلب لاحقاً
                        'subtotal' => $data['subtotal'],
                        'tax'      => $data['tax'],
                        'shipping_cost' => $data['shipping_cost'],
                        'total'    => $data['total'],
                        'payment_method' => 'stripe',
                    ],
                ],
            ]);

            // Cart::where('user_id', $user->id)->delete(); // سننقلها لصفحة النجاح بعد التأكد من الدفع

            // 2. استدعاء بوابة Stripe
            $paymentService = app(PaymentService::class);
            $result = $paymentService->pay($payment, 'card', 'stripe');

            if (!$result->success) {
                throw new \Exception($result->message ?? 'فشل الاتصال بـ Stripe');
            }

            return response()->json([
                'status' => true,
                'payment_required' => true,
                'data' => [
                    'payment_id'   => $payment->id,
                    'redirect_url' => $result->data['redirect_url'] ?? null,
                ]
            ], 201);
        });
    }

    /**
     * تحديث حالة الطلب بعد نجاح الدفع عبر Stripe.
     */
    public function updateStatus(Order $order, User $user, array $data = []): void
    {
        $order->update([
            'status'         => OrderStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
            'notes'          => 'تم الدفع بنجاح عبر Stripe'
        ]);

        (new StockManager())->withdraw($order);
    }
}
