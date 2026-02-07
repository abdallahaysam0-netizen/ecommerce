<?php

namespace App\Payments\Strategies; // تصحيح المسار ليكون في المجلد العام للاستراتيجيات

use App\Models\Order;
use App\Models\User;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus; 

class FawryPaymentStrategy implements OrderUpdateStrategy {
    
    public function updateStatus(Order $order, User $user): void {
        // التحقق من الصلاحية كما فعلنا سابقاً
        if (!$user->isAdmin()) {
            throw new \Exception("عذراً، الأدمن فقط هو من يمكنه تحويل الطلب لحالة انتظار دفع فوري.");
        }

        // منطق فوري الأولي: الطلب ينتظر الدفع
        $order->update([
            'status' => OrderStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PENDING,
            'notes' => 'تم إنشاء كود فوري، في انتظار قيام العميل بالدفع'
        ]);
    }
}