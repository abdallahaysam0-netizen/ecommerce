<?php

namespace App\Payments\Strategies;

use App\Models\Order;
use App\Models\User;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Inventory\StockManager;
use App\Models\Cart;
use App\Models\Payment;
use App\Enum\PaymentProvider;
use App\Payments\PaymentService;
use Illuminate\Support\Facades\DB;

class FawryPaymentStrategy implements OrderUpdateStrategy
{
    public function checkout(User $user, array $data): mixed
    {
        return DB::transaction(function () use ($user, $data) {
            // 1. إنشاء الطلب مباشرة لحجز المنتجات (كما طلب العميل)
            $order = Order::create([
                'user_id'          => $user->id,
                'order_number'     => Order::generateOrderNumber(),
                'subtotal'         => $data['subtotal'],
                'tax'              => $data['tax'],
                'shipping_cost'    => $data['shipping_cost'],
                'total'            => $data['total'],
                'payment_method'   => 'fawry',
                'payment_status'   => PaymentStatus::PENDING,
                'status'           => OrderStatus::PENDING_PAYMENT,
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

            // خصم المخزون لحجز المنتجات لـ 24 ساعة (بناءً على القاعدة السابقة بـ StockManager)
            (new StockManager())->withdraw($order);

            // 2. إنشاء سجل الدفع لربطه بـ Paymob/Fawry
            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id'  => $user->id,
                'provider' => PaymentProvider::PAYMOB,
                'amount'   => $data['total'],
                'currency' => 'EGP',
                'status'   => PaymentStatus::INITIATED,
            ]);

            Cart::where('user_id', $user->id)->delete();

            // 3. الاتصال ببوابة الدفع
            $paymentService = app(PaymentService::class);
            $result = $paymentService->pay($payment, 'fawry', 'paymob');

            if (!$result->success) {
                throw new \Exception($result->message ?? 'فشل الاتصال بفوري');
            }

            return response()->json([
                'status' => true,
                'payment_required' => true,
                'order_id' => $order->id,
                'data' => [
                    'bill_reference' => $result->data['bill_reference'] ?? null,
                ]
            ], 201);
        });
    }

    /**
     * تحديث حالة طلب فوري بمرونة.
     */
    public function updateStatus(Order $order, User $user, array $data = []): void
    {
        $newPaymentStatus = $data['payment_status'] ?? PaymentStatus::PENDING;

        $order->update([
            'status'         => $data['status'] ?? OrderStatus::PENDING_PAYMENT,
            'payment_status' => $newPaymentStatus,
            'notes'          => $data['notes'] ?? 'تم تحديث حالة طلب فوري.'
        ]);

        if ($newPaymentStatus === PaymentStatus::PAID) {
            (new StockManager())->withdraw($order);
        }
    }
}