<?php

namespace App\Payments\Strategies;

// السطرين دول هما "البطل" اللي ناقص في حكايتنا
use App\Models\Order;
use App\Models\User;

interface OrderUpdateStrategy {
    // تحديث حالة الطلب (مثلاً عند الدفع أو الإلغاء)
    public function updateStatus(Order $order, User $user, array $data = []): void;

    // معالجة عملية الـ Checkout الخاصة بكل طريقة دفع
    public function checkout(User $user, array $data): mixed;
}