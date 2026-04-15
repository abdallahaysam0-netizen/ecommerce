<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\DTO\PaymentResult;
use Stripe\StripeClient;
use Exception;

class StripeGateway implements PaymentGateway
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('stripe.secret'));
    }

    public function processPayment(Payment $payment, string $method = 'card'): PaymentResult
    {
        try {
            // إنشاء جلسة دفع (Checkout Session) أو Payment Intent
            // في هذا المثال سنقوم بإنشاء Checkout Session كمثال شائع وسهل
            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => [$method],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'egp',
                        'product_data' => [
                            'name' => 'Order #' . ($payment->order ? $payment->order->order_number : $payment->order_id),
                        ],
                        'unit_amount' => round($payment->amount * 100), // السعر بالسنت
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'metadata' => [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                ],
                // التوجيه إلى الـ API الخاص بنا ليتحقق من الدفع ثم يحول المستخدم للواجهة الأمامية
                'success_url' => url('/api/payment/stripe/success?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/cart', // يعود للسلة في حالة الإلغاء
            ]);

            return new PaymentResult(true, 'Payment session created successfully', [
                'session_id' => $session->id,
                'redirect_url' => $session->url,
            ]);
        } catch (Exception $e) {
            return new PaymentResult(false, $e->getMessage());
        }
    }

    public function refund(string $transactionId, float $amount): bool
    {
        try {
            $this->stripe->refunds->create([
                'payment_intent' => $transactionId,
                'amount' => $amount * 100,
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
