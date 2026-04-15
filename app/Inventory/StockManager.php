<?php

namespace App\Inventory;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockManager
{
    /**
     * القاعدة المركزية لخصم المخزون بناءً على طريقة الدفع والحدث
     * 
     * @param Order $order
     * @param string $event (created | paid)
     */
    public function handle(Order $order, string $event): void
    {
        $method = $order->payment_method;

        // 1. الدفع عند الاستلام (COD): الخصم فور إنشاء الطلب
        if ($method === 'cod' && $event === 'created') {
            $this->withdraw($order);
        }

        // 2. فوري (Fawry): الخصم فور إنشاء الطلب (للحجز) مع إمكانية الإرجاع بعد 24 ساعة
        if ($method === 'fawry' && $event === 'created') {
            $this->withdraw($order);
        }

        // 3. باقي الطرق (Stripe, Visa, etc.): الخصم فقط عند نجاح الدفع
        if (!in_array($method, ['cod', 'fawry']) && $event === 'paid') {
            $this->withdraw($order);
        }
    }

    /**
     * منطق خاص بـ "فوري": إذا مر 24 ساعة ولم يتم الدفع، يتم إرجاع المخزون
     */
    public function cleanupExpiredFawryOrders(): void
    {
        $expiredOrders = Order::where('payment_method', 'fawry')
            ->where('payment_status', 'pending') // أو الحالة التي تعبر عن انتظار الدفع
            ->where('is_inventory_withdrawn', true)
            ->where('created_at', '<=', now()->subHours(24))
            ->get();

        foreach ($expiredOrders as $order) {
            $this->restore($order);
            
            // تحديث حالة الطلب ليعرف السيستم أنه انتهى زمنه
            $order->update([
                'status' => \App\Enum\OrderStatus::CANCELLED,
                'notes'  => 'تم إعادة المخزون تلقائياً لعدم السداد خلال 24 ساعة (Fawry Time-out).'
            ]);
        }
    }

    /**
     * خصم المنتجات من المخزون
     */
    public function withdraw(Order $order): void
    {
        if ($order->is_inventory_withdrawn) {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->decrement('stock', $item->quantity);
                }
            }
            $order->update(['is_inventory_withdrawn' => true]);
        });
    }

    /**
     * إعادة المنتجات للمخزون
     */
    public function restore(Order $order): void
    {
        if (!$order->is_inventory_withdrawn) {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
            }
            $order->update(['is_inventory_withdrawn' => false]);
        });
    }
}
