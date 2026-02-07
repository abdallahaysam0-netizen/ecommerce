<?php

namespace App\Providers;
use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Support\ServiceProvider; 
use Illuminate\Support\Facades\Broadcast;// ⭐⭐⭐ مهم جدًا
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Gateways\PaymobGateway;
use App\Payments\PaymentService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, PaymobGateway::class);

        $this->app->singleton(PaymentService::class, function ($app) {
            return new PaymentService(
                $app->make(PaymentGateway::class)
            );
        });
    }

    public function boot(): void
    {
        // 💡 إجبار مسارات الإشعارات على استخدام حماية Sanctum والـ API
        Broadcast::routes(['middleware' => ['api', 'auth:sanctum']]);
    
        // تسجيل المراقب الخاص بالطلبات
        Order::observe(OrderObserver::class);
    }
}