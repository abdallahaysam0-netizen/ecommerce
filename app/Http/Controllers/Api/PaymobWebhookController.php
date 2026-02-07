<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Product;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymobWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. التحقق من أمان الإشارة القادمة (HMAC) قبل أي معالجة
        if (!$this->validateHmac($request)) {
            Log::warning('Paymob Webhook: محاولة وصول غير مصرح بها (Invalid HMAC).');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();

        // 2. التعامل مع "رابط العودة" (Response Callback) لتوجيه المستخدم للـ React
        if ($request->isMethod('get')) {
            $success = $request->query('success') === 'true';
            return redirect()->to("http://localhost:3000/orders?status=" . ($success ? 'success' : 'failed'));
        }

        // 3. استخراج البيانات الأساسية للعملية (Processed Callback)
        $success = $payload['obj']['success'] ?? false;
        $paymentRecordId = $payload['obj']['order']['merchant_order_id'] ?? null;
        $paymobTransactionId = $payload['obj']['id'] ?? null;

        if (!$paymentRecordId) {
            return response()->json(['message' => 'Payment Record ID missing'], 400);
        }

        $payment = Payment::find($paymentRecordId);
        if (!$payment) {
            return response()->json(['message' => 'Payment record not found'], 404);
        }

        // منع تكرار المعالجة إذا تم تحديث الحالة مسبقاً
        if ($payment->status === PaymentStatus::PAID) {
            return response()->json(['message' => 'Already processed']);
        }

        // 4. في حالة فشل الدفع
        if (!$success) {
            $payment->update(['status' => PaymentStatus::FAILED]);
            return response()->json(['message' => 'Payment failed status updated']);
        }

        // 5. في حالة نجاح الدفع -> تنفيذ العمليات المالية
        DB::beginTransaction();
        try {
            $user = $payment->user;
            $cartItems = Cart::where('user_id', $user->id)->with('product')->get();

            if ($cartItems->isEmpty()) {
                throw new \Exception("سلة العميل فارغة، لا يمكن إنشاء طلب.");
            }

            // أ- إنشاء الطلب النهائي (Order)
            $order = Order::create([
                'user_id'          => $user->id,
                'order_number'     => Order::generateOrderNumber(),
                'subtotal'         => $payment->amount,
                'total'            => $payment->amount,
                'payment_method'   => 'credit_card',
                'payment_status'   => PaymentStatus::PAID,
                'status'           => OrderStatus::CONFIRMED,
                'transaction_id'   => $paymobTransactionId,
                'paid_at'          => now(),
                'shipping_name'    => $payment->metadata['shipping']['shipping_name'] ?? 'N/A',
                'shipping_address' => $payment->metadata['shipping']['shipping_address'] ?? 'N/A',
                'shipping_city'    => $payment->metadata['shipping']['shipping_city'] ?? 'N/A',
                'shipping_phone'   => $payment->metadata['shipping']['shipping_phone'] ?? 'N/A',
                
                // ✅ إضافة هذه الحقول الإجبارية بناءً على الـ Schema الخاص بك
                'shipping_zipcode' => $payment->metadata['shipping']['shipping_zipcode'] ?? '00000',
                'shipping_country' => $payment->metadata['shipping']['shipping_country'] ?? 'Egypt',
                'shipping_state'   => $payment->metadata['shipping']['shipping_state'] ?? 'N/A', // nullable في السكيما لكن يفضل إرساله
            ]);

            // ب- إنشاء العناصر وتحديث المخزون
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

            // ج- تحديث سجل الدفع وربطه بالطلب
            $payment->update([
                'order_id'          => $order->id,
                'status'            => PaymentStatus::PAID,
                'payment_intent_id' => $paymobTransactionId,
                'completed_at'      => now(),
            ]);

            // د- تفريغ السلة
            Cart::where('user_id', $user->id)->delete();

            DB::commit();
            return response()->json(['message' => 'تم إنشاء الطلب وربط الدفع بنجاح']);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('خطأ في معالجة الـ Webhook: ' . $e->getMessage());
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * التحقق من صحة الـ HMAC المرسل من بي موب لضمان أمن البيانات
     */
    private function validateHmac(Request $request)
    {
        $hmac = $request->query('hmac');
        $payload = $request->isMethod('get') ? $request->all() : ($request->all()['obj'] ?? null);
    
        if (!$payload || !$hmac) return false;
    
        // دالة مساعدة لضمان تحويل القيم المنطقية لنصوص "true" أو "false" كما تطلب بي موب
        $parseBool = function($value) {
            if (is_bool($value)) return $value ? 'true' : 'false';
            return $value;
        };
    
        $string = 
            $parseBool($payload['amount_cents'] ?? '') . 
            $parseBool($payload['created_at'] ?? '') . 
            $parseBool($payload['currency'] ?? '') . 
            $parseBool($payload['error_occured'] ?? '') . 
            $parseBool($payload['has_parent_transaction'] ?? '') . 
            $parseBool($payload['id'] ?? '') . 
            $parseBool($payload['integration_id'] ?? '') . 
            $parseBool($payload['is_3d_secure'] ?? '') . 
            $parseBool($payload['is_auth'] ?? '') . 
            $parseBool($payload['is_capture'] ?? '') . 
            $parseBool($payload['is_refunded'] ?? '') . 
            $parseBool($payload['is_standalone_payment'] ?? '') . 
            $parseBool($payload['is_voided'] ?? '') . 
            ($request->isMethod('get') ? $parseBool($payload['order'] ?? '') : $parseBool($payload['order']['id'] ?? '')) . 
            $parseBool($payload['owner'] ?? '') . 
            $parseBool($payload['pending'] ?? '') . 
            ($request->isMethod('get') ? $parseBool($payload['source_data_pan'] ?? $payload['source_data.pan'] ?? '') : $parseBool($payload['source_data']['pan'] ?? '')) . 
            ($request->isMethod('get') ? $parseBool($payload['source_data_sub_type'] ?? $payload['source_data.sub_type'] ?? '') : $parseBool($payload['source_data']['sub_type'] ?? '')) . 
            ($request->isMethod('get') ? $parseBool($payload['source_data_type'] ?? $payload['source_data.type'] ?? '') : $parseBool($payload['source_data']['type'] ?? '')) . 
            $parseBool($payload['success'] ?? '');
    
        $secret = config('services.paymob.hmac_secret');
        $hashing = hash_hmac('sha512', $string, $secret);
    
        return hash_equals($hashing, $hmac);
    }
}