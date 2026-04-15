<?php

namespace App\Payments\Contracts;

use App\Models\Payment; // ✅ استيراد موديل الـ Payment
use App\Payments\DTO\PaymentResult;

interface PaymentGateway
{
    /**
     * Start payment process
     * ✅ تعديل التوقيع ليتوافق مع الكلاس
     */
    public function processPayment(Payment $payment, string $method = 'card'): PaymentResult;
        
    /**
     * Refund payment
     */
    public function refund(string $transactionId, float $amount): bool;
}