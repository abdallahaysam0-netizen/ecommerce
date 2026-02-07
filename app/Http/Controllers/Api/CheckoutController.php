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

class CheckoutController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function checkout(Request $request)
    {
        $request->validate([
            "shipping_name"     => "required|string|max:255",
            "shipping_address"  => "required|string|max:255",
            "shipping_city"     => "required|string|max:255",
            "shipping_state"    => "required|string|max:255",
            "shipping_zipcode"  => "required|string|max:20",
            "shipping_country"  => "required|string|max:255",
            "shipping_phone"    => "required|digits:11",
            "payment_method"    => "required|in:cod,credit_card,fawry,wallet",
            "notes"             => "nullable|string",
        ]);

        $user = $request->user();
        $cartItems = Cart::where('user_id', $user->id)->with('product')->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        // حساب السعر
        $subtotal = 0;
        foreach ($cartItems as $item) {
            if (! $item->product || ! $item->product->is_active) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product not available'
                ], 422);
            }

            if ($item->product->stock < $item->quantity) {
                return response()->json([
                    'status' => false,
                    'message' => 'Insufficient stock'
                ], 422);
            }

            $subtotal += $item->product->price * $item->quantity;
        }

        $tax = round($subtotal * 0.08, 2);
        $shippingCost = 5;
        $total = round($subtotal + $tax + $shippingCost, 2);

        /**
         * ==========================
         * COD → Order مباشر
         * ==========================
         */
        if ($request->payment_method === 'cod') {
            DB::beginTransaction();

            try {
                $order = Order::create([
                    'user_id'          => $user->id,
                    'order_number'     => Order::generateOrderNumber(),
                    'subtotal'         => $subtotal,
                    'tax'              => $tax,
                    'shipping_cost'    => $shippingCost,
                    'total'            => $total,
                    'payment_method'   => 'cod',
                    'payment_status'   => PaymentStatus::PENDING,
                    'status'           => OrderStatus::CONFIRMED,
                    'shipping_name'    => $request->shipping_name,
                    'shipping_address' => $request->shipping_address,
                    'shipping_city'    => $request->shipping_city,
                    'shipping_state'   => $request->shipping_state,
                    'shipping_zipcode' => $request->shipping_zipcode,
                    'shipping_country' => $request->shipping_country,
                    'shipping_phone'   => $request->shipping_phone,
                    'notes'            => $request->notes,
                ]);

                foreach ($cartItems as $item) {
                    $order->items()->create([
                        'product_id'   => $item->product->id,
                        'product_name' => $item->product->name,
                        'price'        => $item->product->price,
                        'quantity'     => $item->quantity,
                        'subtotal'     => $item->product->price * $item->quantity,
                    ]);

                    $item->product->decrement('stock', $item->quantity);
                }

                Cart::where('user_id', $user->id)->delete();

                DB::commit();

                return response()->json([
                    'status' => true,
                    'payment_required' => false,
                    'order_id' => $order->id,
                ], 201);

            } catch (\Throwable $e) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
        }

       
/**
 * ==========================
 * Online Payment → (Card, Fawry, Wallet)
 * ==========================
 */
$payment = Payment::create([
    'user_id'  => $user->id,
    'provider' => PaymentProvider::PAYMOB,
    'amount'   => $total,
    'currency' => 'EGP',
    'status'   => PaymentStatus::INITIATED,
    'metadata' => [
        'shipping' => $request->only([
            'shipping_name', 'shipping_address', 'shipping_city',
            'shipping_state', 'shipping_zipcode', 'shipping_country',
            'shipping_phone', 'notes',
        ]),
    ],
]);

// ✅ 1. تحديد نوع الدفع ديناميكياً بناءً على اختيار المستخدم
$method = 'card'; // افتراضي
if ($request->payment_method === 'fawry') $method = 'fawry';
if ($request->payment_method === 'wallet') $method = 'wallet';

// ✅ 2. تمرير النوع الصحيح للدالة
$result = $this->paymentService->pay($payment, $method);

if (!$result->success) {
    return response()->json([
        'status' => false,
        'message' => $result->message ?? 'حدث خطأ أثناء الاتصال ببوابة الدفع'
    ], 422);
}

// ✅ 3. إرجاع البيانات داخل مصفوفة "data" عشان الـ React ميعملش Crash
return response()->json([
    'status' => true,
    'payment_required' => true,
    'data' => [
        'payment_id'     => $payment->id,
        'iframe_url'     => $result->data['iframe_url'] ?? null, 
        'bill_reference' => $result->data['bill_reference'] ?? null, // كود فوري هنا
        'redirect_url'   => $result->data['redirect_url'] ?? null,   // رابط المحفظة هنا
    ]
], 201);
    }
}
