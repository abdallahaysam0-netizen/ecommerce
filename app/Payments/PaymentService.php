<?php

namespace App\Payments;

use App\Models\Payment;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Gateways\StripeGateway;
use App\Payments\DTO\PaymentResult;

class PaymentService
{
    // 1. يجب تعريف الخاصية هنا أولاً
    protected PaymentGateway $paymob;

    // 2. تأكد أن الاسم في الـ constructor هو نفسه الذي تستخدمه
    public function __construct(PaymentGateway $paymob)
    {
        $this->paymob = $paymob;
    }

    public function pay(Payment $payment, string $method = 'card', string $gateway = 'paymob'): PaymentResult
    {
        $paymentGateway = $this->resolveGateway($gateway);
        return $paymentGateway->processPayment($payment, $method);
    }

    public function refund(string $transactionId, float $amount, string $gateway = 'paymob'): bool
    {
        $paymentGateway = $this->resolveGateway($gateway);
        return $paymentGateway->refund($transactionId, $amount);
    }

    protected function resolveGateway(string $gateway): PaymentGateway
    {
        if ($gateway === 'stripe') {
            return app(StripeGateway::class);
        }

        // الافتراضي هو بوابات الدفع paymob
        return $this->paymob;
    }
}