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

use App\Inventory\StockManager;

class PaymobWebhookController extends Controller
{
    protected StockManager $stockManager;

    public function __construct(StockManager $stockManager)
    {
        $this->stockManager = $stockManager;
    }

    public function handle(Request $request)
    {
        // 1. Handle Response Callback / Redirection
        if ($request->isMethod('get')) {
            if (!$this->validateHmac($request)) {
                Log::warning('Paymob Redirect: HMAC validation failed for redirection URL.');
            }
            $success = $request->query('success') === 'true';
            return redirect()->to(env('FRONTEND_URL', 'http://localhost:3000') . "/orders?status=" . ($success ? 'success' : 'failed'));
        }

        // 2. التحقق من أمان الإشارة للـ Webhook (POST) - هنا إلزامي للأمان
        if (!$this->validateHmac($request)) {
            Log::error('Paymob Webhook: Unauthorized access attempt (Invalid HMAC).');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();

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
            $order = $payment->order;

            // إذا لم يكن هناك طلب مرتبط بعد (الحالة الجديدة)، نقوم بإنشائه من الـ Metadata
            if (!$order) {
                $checkoutData = $payment->metadata['checkout_data'] ?? null;
                if (!$checkoutData) {
                    throw new \Exception("بيانات الطلب مفقودة في سجل الدفع (Metadata).");
                }

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
                    'transaction_id'   => $paymobTransactionId,
                    'paid_at'          => now(),
                ]);

                // ربط الدفع بالطلب المكتمل
                $payment->update(['order_id' => $order->id]);

                // إنشاء عناصر الطلب وخصم المخزون
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

            // تحديث سجل الدفع
            $payment->update([
                'status'            => PaymentStatus::PAID,
                'payment_intent_id' => $paymobTransactionId,
                'completed_at'      => now(),
            ]);

            DB::commit();
            return response()->json(['message' => 'تم إنشاء الطلب وتأكيد الدفع بنجاح']);

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

        if (!hash_equals($hashing, $hmac)) {
            Log::debug('Paymob HMAC Debug:', [
                'generated_string' => $string,
                'calculated_hmac' => $hashing,
                'received_hmac' => $hmac,
                'method' => $request->method()
            ]);
            return false;
        }
    
        return true;
    }
}