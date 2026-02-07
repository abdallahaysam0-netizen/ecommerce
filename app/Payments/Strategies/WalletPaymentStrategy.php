<?php
namespace App\Payments\Strategies\WalletPaymentStrategy;

use App\Models\Order;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;

class WalletPaymentStrategy implements OrderUpdateStrategy {
    public function updateStatus(Order $order): void {
        // منطق المحفظة: حالة الطلب تصبح "قيد التنفيذ" والدفع "مكتمل"
        $order->update([
            'status' => OrderStatus::PROCESSING,
            'payment_status' => PaymentStatus::PAID,
            'notes' => 'تم الدفع عبر المحفظة بنجاح'
        ]);
    }
}
