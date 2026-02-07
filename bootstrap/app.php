<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\EnsureUserIsDelivery;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 1️⃣ 🛡️ استثناء مسار الـ Webhook الخاص بـ Paymob من حماية CSRF
        // ضروري جداً لكي تستقبل الإشارات من سيرفر بي موب بنجاح
        $middleware->validateCsrfTokens(except: [
            'api/paymob/webhook',
        ]);

        // 2️⃣ 🔑 تعريف الأسماء المستعارة للميدل وير الخاص بالأدوار والصلاحيات
        $middleware->alias([
            'admin'      => EnsureUserIsAdmin::class,
            'isCustomer' => EnsureUserIsCustomer::class,

            'permission' => CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 3️⃣ 🚫 تخصيص رد JSON عند محاولة الوصول لمسار محمي بدون تسجيل دخول
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
        });
    })
    ->create();