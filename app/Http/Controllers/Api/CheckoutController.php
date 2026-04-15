<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Enum\PaymentProvider;
use App\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;
use App\Inventory\StockManager;

class CheckoutController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected StockManager $stockManager
    ) {}

    public function checkout(Request $request)
    {
        $request->validate([
            "shipping_name"     => "required|string|max:255",
            "shipping_address"  => "required|string|max:255",
            "shipping_city"     => "nullable|string|max:255",
            "shipping_state"    => "nullable|string|max:255",
            "shipping_zipcode"  => "nullable|string|max:20",
            "shipping_country"  => "required|string|max:255",
            "shipping_phone"    => "required|digits:11",
            "payment_method"    => "required|in:cod,credit_card,fawry,stripe",
            "notes"             => "nullable|string",
        ]);

        $user = $request->user();
        $cartItems = Cart::where('user_id', $user->id)->with('product')->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'Cart is empty'], 400);
        }

        // 1. الحسابات الأساسية (Common Logic)
        $subtotal = 0;
        foreach ($cartItems as $item) {
            if (!$item->product || !$item->product->is_active) {
                return response()->json(['status' => false, 'message' => 'Product not available'], 422);
            }
            if ($item->product->stock < $item->quantity) {
                return response()->json(['status' => false, 'message' => 'Insufficient stock'], 422);
            }
            $subtotal += $item->product->price * $item->quantity;
        }

        $tax = round($subtotal * 0.08, 2);
        $shippingCost = 5;
        $total = round($subtotal + $tax + $shippingCost, 2);

        // لقطة للمنتجات (Snapshot) لاستخدامها في الاستراتيجيات
        $itemsSnapshot = $cartItems->map(function($item) {
            return [
                'product_id'   => $item->product_id,
                'product_name' => $item->product->name,
                'price'        => $item->product->price,
                'quantity'     => $item->quantity,
            ];
        })->toArray();

        // 2. اختيار الاستراتيجية المناسبة
        $strategy = match($request->payment_method) {
            'cod'         => new \App\Payments\Strategies\CodPaymentStrategy(),
            'fawry'       => new \App\Payments\Strategies\FawryPaymentStrategy(),
            'stripe'      => new \App\Payments\Strategies\StripePaymentStrategy(),
            'credit_card' => new \App\Payments\Strategies\VisaPaymentStrategy(),
        };

        // 3. تنفيذ الـ Checkout الخاص بكل طريقة (Decentralized)
        return $strategy->checkout($user, [
            'subtotal'      => $subtotal,
            'tax'           => $tax,
            'shipping_cost' => $shippingCost,
            'total'         => $total,
            'shipping'      => $request->only([
                'shipping_name', 'shipping_address', 'shipping_city',
                'shipping_state', 'shipping_zipcode', 'shipping_country',
                'shipping_phone', 'notes'
            ]),
            'items'         => $cartItems, // للكاش وفوري (تحتاج الموديلات)
            'items_snapshot' => $itemsSnapshot, // للأونلاين (تحتاج مصفوفة بيانات)
        ]);
    }

    public function stripeSuccess(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/cart?error=missing_session');
        }

        try {
            $stripe = new \Stripe\StripeClient(config('stripe.secret'));
            $session = $stripe->checkout->sessions->retrieve($sessionId);
            $paymentId = $session->metadata->payment_id ?? null;

            if ($paymentId && $session->payment_status === 'paid') {
                $payment = Payment::with('order')->find($paymentId);

                if ($payment && $payment->status !== PaymentStatus::PAID) {
                    DB::beginTransaction();
                    try {
                        $order = $payment->order;

                        // إذا لم يكن الطلب موجوداً (الحالة الجديدة)، نقوم بإنشائه الآن
                        if (!$order) {
                            $checkoutData = $payment->metadata['checkout_data'] ?? null;
                            if (!$checkoutData) throw new \Exception("Metadata missing");

                            $shipping = $checkoutData['shipping'];

                            $order = Order::create([
                                'user_id'          => $payment->user_id,
                                'order_number'     => Order::generateOrderNumber(),
                                'subtotal'         => $checkoutData['subtotal'],
                                'tax'              => $checkoutData['tax'],
                                'shipping_cost'    => $checkoutData['shipping_cost'],
                                'total'            => $checkoutData['total'],
                                'payment_method'   => $checkoutData['payment_method'],
                                'payment_status'   => PaymentStatus::PAID,
                                'status'           => OrderStatus::CONFIRMED,
                                'shipping_name'    => $shipping['shipping_name'],
                                'shipping_address' => $shipping['shipping_address'],
                                'shipping_city'    => $shipping['shipping_city'],
                                'shipping_state'   => $shipping['shipping_state'],
                                'shipping_zipcode' => $shipping['shipping_zipcode'],
                                'shipping_country' => $shipping['shipping_country'],
                                'shipping_phone'   => $shipping['shipping_phone'],
                                'notes'            => $shipping['notes'],
                                'transaction_id'   => $session->payment_intent,
                                'paid_at'          => now(),
                            ]);

                            $payment->update(['order_id' => $order->id]);

                            foreach ($checkoutData['items'] as $item) {
                                $order->items()->create([
                                    'product_id'   => $item['product_id'],
                                    'product_name' => $item['product_name'],
                                    'price'        => $item['price'],
                                    'quantity'     => $item['quantity'],
                                    'subtotal'     => $item['price'] * $item['quantity'],
                                ]);
                            }

                            $this->stockManager->withdraw($order);
                            Cart::where('user_id', $payment->user_id)->delete();
                        }

                        // تحديث حالة الدفع إلى مكتمل
                        $payment->markAsCompleted($session->payment_intent, $session->metadata->toArray());

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        throw $e;
                    }
                }

                // التوجيه لصفحة الطلبات بنجاح
                return redirect(env('FRONTEND_URL', 'http://localhost:3000') . "/orders?success=true");
            }

        } catch (\Exception $e) {
            return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/cart?error=verification_failed');
        }
    }
}