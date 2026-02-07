<?php

namespace App\Observers;

use App\Models\Order;
use App\Notifications\OrderStatusChanged;
class OrderObserver
{
    

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // التحقق مما إذا كان حقل الحالة (status) هو الذي تغير فعلياً
        if ($order->wasChanged('status')) {
            // ✅ إضافة الفاصلة المنقوطة في النهاية
            $order->user->notify(new OrderStatusChanged($order)); 
        }
    }

}
