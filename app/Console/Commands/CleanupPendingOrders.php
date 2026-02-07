<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use Illuminate\Support\Facades\DB;

class CleanupPendingOrders extends Command
{
    protected $signature = 'orders:cleanup-pending';
    protected $description = 'حذف الطلبات غير المكتملة (DRAFT/PENDING) التي لم يتم دفعها ومر عليها 48 ساعة';

    public function handle()
    {
        $threshold = now()->subHours(48);

        // 1. بناء الاستعلام مع الربط بين حالة الطلب وحالة الدفع
        $query = Order::whereIn('status', [
            OrderStatus::DRAFT->value,
            OrderStatus::PENDING_PAYMENT->value
        ])
        ->where('created_at', '<', $threshold)
        // شرط إضافي للأمان: لا تحذف الطلب إذا كان هناك عملية دفع ناجحة مرتبطة به
        ->whereDoesntHave('payments', function ($q) {
            $q->where('status', PaymentStatus::PAID->value);
        });

        $count = $query->count();

        if ($count === 0) {
            $this->info('لا توجد طلبات قديمة تحتاج للتنظيف حالياً.');
            return Command::SUCCESS;
        }

        // 2. التنفيذ
        DB::transaction(function () use ($query) {
            $query->delete();
        });

        $this->warn("تم حذف {$count} طلب (لم تكتمل عملية دفعهم) بنجاح.");
        
        return Command::SUCCESS;
    }
}