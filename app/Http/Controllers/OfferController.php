<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache; // تم إضافة استدعاء الكاش

class OfferController extends Controller
{
    public function index()
    {
        // استخدام رقم الصفحة في مفتاح الكاش لضمان صحة الترقيم (Pagination)
        $page = request()->get('page', 1);
        $cacheKey = "flash_offers_page_{$page}";

        $products = Cache::remember($cacheKey, 3600, function () {
            $data = Product::with('categories')
                ->whereNotNull('discount_price')
                ->whereColumn('discount_price', '<', 'price')
                ->where('is_active', true)
                ->paginate(4);

            $data->getCollection()->transform(function ($product) {
                return $this->formatOfferProduct($product);
            });

            return $data;
        });

        return response()->json([
            "success" => true,
            "message" => "Flash offers retrieved successfully",
            "data" => $products,
        ]);
    }

    private function formatOfferProduct(Product $product)
    {
        // المبلغ المخصوم (مثلاً 2000) كما هو في كودك الأصلي
        $discountAmount = (float) $product->discount_price; 
        
        // السعر النهائي = السعر الأصلي - المبلغ المخصوم كما هو في كودك الأصلي
        $finalPrice = (float) $product->price - $discountAmount;

        // حساب النسبة المئوية بناءً على المبلغ المخصوم كما هو في كودك الأصلي
        $discountPercentage = 0;
        if ($product->price > 0) {
            $discountPercentage = round(($discountAmount / $product->price) * 100);
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'original_price' => (float) $product->price,
            'discount_amount' => $discountAmount, // الـ 2000 جنيه
            'offer_price' => $finalPrice,         // السعر بعد الخصم
            'discount_percentage' => $discountPercentage . '%',
            'image' => $product->image ? asset('storage/' . $product->image) : null,
            // ... باقي الحقول
        ];
    }
}