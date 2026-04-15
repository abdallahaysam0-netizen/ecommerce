<?php

namespace App\Payments\Strategies;

use App\Models\Order;
use App\Models\User;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use Illuminate\Support\Facades\Log;

use App\Inventory\StockManager;
use App\Models\Cart;
use Illuminate\Support\Facades\DB;

class CodPaymentStrategy implements OrderUpdateStrategy
{
    public function checkout(User $user, array $data): mixed
    {
        return DB::transaction(function () use ($user, $data) {
            $order = Order::create([
                'user_id'          => $user->id,
                'order_number'     => Order::generateOrderNumber(),
                'subtotal'         => $data['subtotal'],
                'tax'              => $data['tax'],
                'shipping_cost'    => $data['shipping_cost'],
                'total'            => $data['total'],
                'payment_method'   => 'cod',
                'payment_status'   => PaymentStatus::PENDING,
                'status'           => OrderStatus::PENDING_PAYMENT, // الكاش يتسجل كأنه مؤكد انتظاراً للتوصيل
                'shipping_name'    => $data['shipping']['shipping_name'],
                'shipping_address' => $data['shipping']['shipping_address'],
                'shipping_city'    => $data['shipping']['shipping_city'],
                'shipping_state'   => $data['shipping']['shipping_state'],
                'shipping_zipcode' => $data['shipping']['shipping_zipcode'],
                'shipping_country' => $data['shipping']['shipping_country'],
                'shipping_phone'   => $data['shipping']['shipping_phone'],
                'notes'            => $data['shipping']['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_id'   => $item->product->id,
                    'product_name' => $item->product->name,
                    'price'        => $item->product->price,
                    'quantity'     => $item->quantity,
                    'subtotal'     => $item->product->price * $item->quantity,
                ]);
            }

            (new StockManager())->withdraw($order);
            Cart::where('user_id', $user->id)->delete();

            return response()->json([
                'status' => true,
                'payment_required' => false,
                'order_id' => $order->id,
            ], 201);
        });
    }

    public function updateStatus(Order $order, User $user, array $data = []): void 
    {
    // 2. تحديث مرن بناءً على البيانات المرسلة أو قيم افتراضية
    $newStatus = $data['status'] ?? OrderStatus::PENDING_PAYMENT;
    $order->update([
        'status'         => $newStatus,
        'payment_status' => $data['payment_status'] ?? PaymentStatus::PENDING,
        'notes'          => $data['notes'] ?? 'تم تحديث الحالة بواسطة الأدمن - نظام COD'
    ]);

    // إذا تم تأكيد الطلب، نقوم بخصم المخزون (لو لم يتم خصمه مسبقاً)
    if ($newStatus === OrderStatus::PENDING_PAYMENT) {
        (new \App\Inventory\StockManager())->withdraw($order);
    }
}
}