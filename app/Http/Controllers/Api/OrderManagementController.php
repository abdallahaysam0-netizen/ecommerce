<?php

namespace App\Http\Controllers\Api;

use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Payments\PaymentService;
use App\Payments\Strategies\VisaPaymentStrategy;
use App\Payments\Strategies\FawryPaymentStrategy;   
use App\Payments\Strategies\WalletPaymentStrategy;
use App\Payments\Strategies\CodPaymentStrategy;
class OrderManagementController extends Controller
{
    // 1️⃣ تعريف الخاصية لكي يراها الكنترولر
    protected $paymentService;

    // 2️⃣ حقن الخدمة داخل دالة البناء
    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }
    // 🔹 عرض جميع الطلبات للمستخدم أو admin
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->admin) {
            $orders = Order::with('items')->latest()->get();
        } else {
            $orders = Order::with('items')->where('user_id', $user->id)->latest()->get();
        }

        return response()->json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    // 🔹 عرض تفاصيل الطلب
    public function show(Order $order, Request $request)
    {
        $user = $request->user();
    
        // جلب الـ order مع الـ items والـ product لكل item
        $order->load('items.product');
    
        // التأكد من صلاحية الوصول
        if (!$user->is_admin && $order->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
    
        return response()->json([
            'success' => true,
            'order' => $order
        ]);
    }
  
public function updateStatus(Request $request, Order $order)
{
    try {
        // 1. التحقق من وجود مستخدم مسجل دخول (Auth Check)
        $user = $request->user();
       if (!$user->isAdmin()) {
            throw new \Exception("لم يتم التعرف على المستخدم. تأكد من إرسال الـ Token بشكل صحيح.");
        }

        // 2. تتبع قيمة الـ admin (للتصحيح فقط - يمكنك حذفه لاحقاً)
        // \Illuminate\Support\Facades\Log::info("User ID: {$user->id}, Admin Value: " . ($user->admin ? 'True' : 'False'));

        // 3. تعريف طريقة الدفع من بيانات الطلب
        $paymentMethod = $order->payment_method; 

        // 4. اختيار الاستراتيجية المناسبة
        $strategy = match($paymentMethod) {
            'visa'   => new VisaPaymentStrategy(),
            'fawry'  => new FawryPaymentStrategy(),
            'wallet' => new WalletPaymentStrategy(),
            'cod'    => new CodPaymentStrategy(),
            default  => throw new \Exception("طريقة دفع غير مدعومة: {$paymentMethod}")
        };

        // 5. تنفيذ التحديث
        $strategy->updateStatus($order, $user);

        return response()->json([
            'success' => true, 
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'order'   => $order->fresh()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false, 
            'message' => $e->getMessage()
        ], 403); 
    }
}
// 🔹 إلغاء الطلب
public function cancel(Order $order, Request $request)
{
    $user = $request->user();

    // 1. التحقق من الصلاحية (Admin أو صاحب الطلب)
    if (!$user->is_admin && $order->user_id !== $user->id) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    // 2. التحقق هل الحالة تسمح بالإلغاء بناءً على الـ Enum logic
    if (!$order->canBeCancelled()) {
        return response()->json([
            'success' => false, 
            'message' => "لا يمكن إلغاء الطلب في حالته الحالية: {$order->status->value}"
        ], 422);
    }

    DB::beginTransaction();
    try {
        // 3. التعامل مع المستردات المالية (Refund) لعمليات الفيزا
        if ($order->payment_method !== 'cod' && $order->payment_status === PaymentStatus::PAID) {
            
            if (!$order->transaction_id) {
                throw new \Exception("لم يتم العثور على رقم العملية (Transaction ID) لإتمام الاسترداد.");
            }

            // طلب الاسترداد من بوابة Paymob
            $refundResult = $this->paymentService->refund($order->transaction_id, $order->total);

            if (!isset($refundResult['success']) || $refundResult['success'] !== true) {
                $errorMsg = $refundResult['data']['message'] ?? 'فشل الاتصال ببوابة الدفع';
                throw new \Exception("Paymob Refund Failed: " . $errorMsg);
            }

            // تحديث سجل الدفع المرتبط
            Payment::where('order_id', $order->id)->update([
                'status' => PaymentStatus::REFUNDED,
                'completed_at' => now(), // توثيق وقت الاسترداد
            ]);
        }

        // 4. تحديث حالة الطلب وحالة الدفع (تمرير الـ Enum مباشرة)
        $order->update([
            'status'         => OrderStatus::CANCELLED,
            'payment_status' => ($order->payment_method === 'cod') ? PaymentStatus::FAILED : PaymentStatus::REFUNDED
        ]);

        // 5. إرجاع المخزون (Inventory Restock)
        foreach ($order->items as $item) {
            $item->product->increment('stock', $item->quantity);
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الطلب وإرجاع المنتجات للمخزون بنجاح',
            'order' => $order->fresh(['items'])
        ]);

    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('Order Cancellation Error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'فشل الإلغاء: ' . $e->getMessage()
        ], 500);
    }
}
}
