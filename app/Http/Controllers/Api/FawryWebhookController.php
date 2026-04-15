<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Payments\Strategies\FawryPaymentStrategy;

use App\Inventory\StockManager;

class FawryWebhookController extends Controller
{
    public function __construct(protected StockManager $stockManager)
    {
    }

    /**
     * معالجة إشعار الدفع القادم من فوري (Stage 2).
     */
    public function handle(Request $request)
    {
        // 1. تسجيل البيانات القادمة للمتابعة
        Log::info('Fawry Webhook Received:', $request->all());

        // 2. التحقق من البيانات الأساسية (هذا مثال هيكلي وقد يختلف حسب API فوري الفعلي)
        // نفترض أن فوري ترسل الـ Merchant Reference (رقم الطلب) وحالة الدفع
        $orderId = $request->input('merchantRefNumber') ?? $request->input('order_id');
        $fawryStatus = $request->input('orderStatus'); // مثال: PAID

        if (!$orderId) {
            return response()->json(['message' => 'Order reference missing'], 400);
        }

        // 3. البحث عن الطلب
        $order = Order::find($orderId);

        if (!$order) {
            Log::error("Fawry Webhook: Order #{$orderId} not found.");
            return response()->json(['message' => 'Order not found'], 404);
        }

        // 4. تنفيذ التحديث باستخدام الاستراتيجية
        try {
            // التحقق من حالة انتهاء الصلاحية أو الإلغاء
            if ($fawryStatus === 'EXPIRED' || $fawryStatus === 'CANCELED') {
                if ($order->status !== OrderStatus::CANCELLED) {
                    DB::beginTransaction();
                    try {
                        // 1. إعادة المنتجات للمخزون
                        $this->stockManager->restore($order);

                        // 2. تحديث حالة الطلب إلى ملغي
                        $order->update([
                            'status'         => OrderStatus::CANCELLED,
                            'payment_status' => PaymentStatus::FAILED,
                            'notes'          => 'تم إلغاء الطلب وإعادة المخزون بسبب انتهاء صلاحية رقم فوري.'
                        ]);

                        DB::commit();
                        return response()->json(['message' => 'Order cancelled and stock restored']);
                    } catch (\Exception $e) {
                        DB::rollBack();
                        throw $e;
                    }
                }
                return response()->json(['message' => 'Order already cancelled']);
            }

            $strategy = new FawryPaymentStrategy();
            
            // نمرر البيانات التي تجعل الاستراتيجية تحول الحالة لـ PAID
            $strategy->updateStatus($order, $order->user, [
                'status'         => OrderStatus::CONFIRMED,
                'payment_status' => PaymentStatus::PAID,
                'notes'          => 'تم تأكيد الدفع تلقائياً عبر فوري (Webhook).'
            ]);

            return response()->json(['message' => 'Order updated successfully']);

        } catch (\Exception $e) {
            Log::error('Fawry Webhook Error: ' . $e->getMessage());
            return response()->json(['message' => 'Error processing update', 'error' => $e->getMessage()], 500);
        }
    }
}
