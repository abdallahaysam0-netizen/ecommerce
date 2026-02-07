<?php

namespace App\Payments\Strategies;

use App\Models\Order;
use App\Models\User;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use Illuminate\Support\Facades\Log;

class CodPaymentStrategy implements OrderUpdateStrategy
{
    /**
     * تحديث حالة طلب "الدفع عند الاستلام" بواسطة الأدمن فقط.
     */
  
        // 1. التأكد من أن المستخدم أدمن قبل تنفيذ أي كود
        // نفترض أن لديك خاصية is_admin أو دالة hasRole في موديل User
     // app/Payments/Strategies/CodPaymentStrategy.php

public function updateStatus(Order $order, User $user): void {
    // التعديل هنا: غيرنا is_admin لـ admin
  // تأكد من وجود الأقواس () لأنها دالة وليست عموداً
    if (!$user->isAdmin()) { 
        throw new \Exception("عذراً، هذا الحساب من نوع ({$user->type}) ولا يملك صلاحية الأدمن.");
    }

    $order->update([
        'status'         => OrderStatus::PROCESSING,
        'payment_status' => PaymentStatus::PENDING,
        'notes'          => 'تم تحديث الحالة بواسطة الأدمن - نظام COD'
    ]);
}
}