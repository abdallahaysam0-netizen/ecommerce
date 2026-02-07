<?php

namespace App\Payments\Strategies;

// السطرين دول هما "البطل" اللي ناقص في حكايتنا
use App\Models\Order;
use App\Models\User;

interface OrderUpdateStrategy {
    // كدة الـ PHP عرف إن الـ User هو الموديل الأساسي مش حد تاني
    public function updateStatus(Order $order, User $user): void;
}