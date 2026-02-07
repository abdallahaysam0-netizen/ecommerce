<?php

namespace App\Payments;

use App\Models\Payment;
use App\Payments\Contracts\PaymentGateway;
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

    public function pay(Payment $payment, string $method = 'card'): PaymentResult
    {
        // السطر 32 كان يشتكي لأن $this->paymob غير موجودة أو مسماة بشكل خاطئ
        return $this->paymob->authorize($payment, $method);
    }

    public function refund(string $transactionId, int $amount): bool
    {
        return $this->paymob->refund($transactionId, $amount);
    }
}