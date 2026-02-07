<?php
namespace App\Payments\Strategies\VisaPaymentStrategy;

use App\Models\Order;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;

class VisaPaymentStrategy implements OrderUpdateStrategy {
    public function updateStatus(Order $order): void {
        // منطق الفيزا: حالة الطلب تصبح "قيد التنفيذ" والدفع "مكتمل"
        $order->update([
            'status' => OrderStatus::PROCESSING,
            'payment_status' => PaymentStatus::PAID,
            'notes' => 'تم الدفع عبر الفيزا بنجاح'
        ]);
    }
}