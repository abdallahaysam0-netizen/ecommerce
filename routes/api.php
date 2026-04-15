<?php

use Illuminate\Http\Request;

use App\Http\Controllers\Api\Auth\AuthController;

use App\Http\Controllers\Api\CartController;

use App\Http\Controllers\Api\CheckoutController;

use App\Http\Controllers\Api\OrderManagementController;

use App\Http\Controllers\Api\PaymentController;

use App\Http\Controllers\Api\PaymobWebhookController;

use App\Http\Controllers\Api\ProductController;

use App\Http\Controllers\Api\RefundController;

use App\Http\Controllers\Api\UserController;

use App\Http\Controllers\OfferController;

use App\Http\Controllers\CategoryController;

use App\Models\Order;

use App\Models\Product;

use Illuminate\Support\Facades\Route;



/*

|--------------------------------------------------------------------------

| Public Routes (No Authentication Required)

|--------------------------------------------------------------------------

*/



// Auth: تسجيل مستخدم جديد أو تسجيل دخول موحد

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);



// Products - Public (Read Only)

Route::get('/products/search', [ProductController::class, 'search']);

Route::get('/products/category/{categoryId}', [ProductController::class, 'productsByCategory']);

Route::apiResource('products', ProductController::class)->only(['index', 'show']);



// Categories - Public (Read Only)

Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);

Route::get('/categories/{id}/products', [CategoryController::class, 'products']);







/*

|--------------------------------------------------------------------------

| Authenticated Routes (Authentication Required)

|--------------------------------------------------------------------------

*/

Route::middleware('auth:sanctum')->prefix('auth')->name('auth.')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/me', [AuthController::class, 'me'])->name('me');

    Route::get('/token', [AuthController::class, 'getAccessToken'])->name('token');

});





/*

    |--------------------------------------------------------------------------

    | Admin-only Routes

    |--------------------------------------------------------------------------

    */

    Route::middleware('admin')->group(function () {

Route::middleware(['auth:sanctum', 'permission:view users'])->group(function () {

    Route::get('/users', [UserController::class, 'index']);

});



Route::middleware(['auth:sanctum', 'permission:create users'])->group(function () {

    Route::post('/users', [UserController::class, 'store']);

});







Route::middleware(['auth:sanctum', 'permission:delete users'])->group(function () {

    Route::delete('/users/{id}', [UserController::class, 'destroy']);

});


     

        // Products Management

    Route::prefix('products')->middleware('auth:sanctum')->group(function () {

    Route::post('/', [ProductController::class, 'store'])->middleware('permission:create products');

    Route::put('/{product}', [ProductController::class, 'update'])->middleware('permission:edit products');

    Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('permission:delete products');

    Route::post('/{product}/restore', [ProductController::class, 'undoDelete'])->middleware('permission:restore products');

    Route::get('/admin', [ProductController::class, 'adminIndex'])->middleware('permission:view admin products');

    Route::delete('/permanent', [ProductController::class, 'forceDelete'])->middleware('permission:delete products');

});

// Categories Management
        Route::prefix('categories')->group(function () {

            Route::post('/', [CategoryController::class, 'store'])->middleware('permission:create categories');

            Route::put('/{category}', [CategoryController::class, 'update'])->middleware('permission:edit categories');

            Route::delete('/{category}', [CategoryController::class, 'destroy'])->middleware('permission:delete categories');

        });

/*

    |--------------------------------------------------------------------------

    | Customer & Admin Routes (Authenticated Users)

    |--------------------------------------------------------------------------

    */


    // Cart Management

Route::middleware('auth:sanctum')->prefix('cart')->group(function () {

    Route::get('/', [CartController::class, 'index']);

    Route::post('/', [CartController::class, 'store']);

    Route::put('/{cart}', [CartController::class, 'update']);

    Route::delete('/{cart}', [CartController::class, 'destroy']);

});

//  سe::get('/orders/{order}', [CheckoutController::class, 'orderDetails']);

});






// ✅ مسارات الدفع والـ Webhooks (بيني وبين بوابات الدفع)
Route::match(['get', 'post'], 'paymob/webhook', [PaymobWebhookController::class, 'handle']);
Route::post('fawry/webhook', [\App\Http\Controllers\Api\FawryWebhookController::class, 'handle']);
Route::get('payment/stripe/success', [CheckoutController::class, 'stripeSuccess']);
    
 // 🔹 Checkout & Payment (يجب تسجيل الدخول)

Route::middleware('auth:sanctum')->group(function () {



    // Checkout → إنشاء Cart أو PaymentSession

    Route::post('checkout', [CheckoutController::class, 'checkout']);



    // Payment → دفع الكارت

    Route::post('payment/{paymentId}/pay', [PaymentController::class, 'pay']);



    // Orders → الإدارة للمستخدم أو admin

    Route::prefix('orders')->group(function () {    

        Route::get('/', [OrderManagementController::class, 'index']);

        Route::get('/{order}', [OrderManagementController::class, 'show']);

        Route::patch('/{order}/status', [OrderManagementController::class, 'updateStatus']); 

        Route::post('/{order}/cancel', [OrderManagementController::class, 'cancel']);
    }); // إغلاق مجموعة الـ prefix

    // Refund Route
    Route::post('/refund', [RefundController::class, 'refund']);

});
 // إغلاق مجموعة الـ middleware
// الإحصائيات
Route::get('/admin/stats', function () {
    $sales = Order::sum('total');
    $orders = Order::count();
    $products = Product::count();
    $monthlySales = Order::selectRaw('MONTH(created_at) as month, SUM(total) as sales')
        ->groupBy('month')->get()->map(fn($o) => [
            'name' => date('M', mktime(0, 0, 0, $o->month, 1)),
            'sales' => $o->sales,
        ]);
    return response()->json(['sales' => $sales, 'orders' => $orders, 'products' => $products, 'monthlySales' => $monthlySales]);
});
Route::middleware('auth:sanctum')->get('/notifications', function (Request $request) {
    return $request->user()->notifications()->limit(10)->get()->map(function ($n) {
        return [
            'id' => $n->id,
            'order_id' => $n->data['order_id'] ?? '#', // ✅ جلب رقم الطلب
            // تأكد إن المفتاح هنا اسمه message عشان الـ React يشوفه
            'message' => $n->data['message'] ?? 'تحديث جديد', 
            'created_at' => $n->created_at->diffForHumans(),
        ];
    });
});
// مسار العروض منفصل تماماً
Route::get('products-offers', [OfferController::class, 'index']);


