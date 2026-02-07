<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    // ======================
    // 1. عرض جميع المنتجات مع الفئات (تم إضافة الكاش)
    // ======================
    public function index()
    {
        // الترقيم يحتاج مفتاح كاش مختلف لكل صفحة
        $page = request()->get('page', 1);
        $cacheKey = "products_index_page_{$page}";

        $products = Cache::remember($cacheKey, 3600, function () {
            $data = Product::with('categories')
                ->latest()
                ->paginate(4);

            $data->getCollection()->transform(function ($product) {
                return $this->formatProduct($product);
            });

            return $data;
        });

        return response()->json([
            "success" => true,
            "message" => "Products retrieved successfully",
            "data" => $products,
        ]);
    }

    // ======================
    // 2. عرض منتجات حسب فئة معينة (تم إضافة الكاش)
    // ======================
    public function productsByCategory($categoryId)
    {
        $page = request()->get('page', 1);
        $cacheKey = "products_category_{$categoryId}_page_{$page}";

        $products = Cache::remember($cacheKey, 3600, function () use ($categoryId) {
            $data = Product::whereHas('categories', function($q) use ($categoryId){
                    $q->where('categories.id', $categoryId)
                      ->where('categories.is_active', true);
                })
                ->with('categories')
                ->paginate(4);

            $data->getCollection()->transform(function ($product) {
                return $this->formatProduct($product);
            });

            return $data;
        });

        return response()->json([
            "success" => true,
            "message" => "Products by category",
            "data" => $products,
        ]);
    }

    // ======================
    // 3. عرض منتج واحد بالتفصيل (تم إضافة الكاش)
    // ======================
    public function show(Product $product)
    {
        $cacheKey = "product_detail_{$product->id}";

        $data = Cache::remember($cacheKey, 3600, function () use ($product) {
            $product->load('categories');
            return $this->formatProduct($product);
        });

        return response()->json([
            "success" => true,
            "message" => "Product retrieved successfully",
            "data" => $data,
        ]);
    }

    // ======================
    // 4. إضافة منتج جديد (تم إضافة مسح الكاش)
    // ======================
    public function store(Request $request)
    {
        $data = $request->validate([
            "name" => "required|string|max:255",
            "slug" => "required|string|max:255|unique:products",
            "description" => "nullable|string",
            "price" => "required|numeric|min:0",
            "discount_price" => "nullable|numeric|min:0", 
            "stock" => "required|integer|min:0",
            "sku" => "required|string|max:255|unique:products",
            "is_active" => "boolean",
            "categories" => "sometimes|array|exists:categories,id",
            "image" => "nullable|image|mimes:jpeg,png,jpg|max:2048",
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->storeAs('products', $data['slug'], 'public');
        }

        $product = Product::create($data);

        if (!empty($data['categories'])) {
            $product->categories()->attach($data['categories']);
        }

        $product->load('categories');
        
        // مسح كاش القوائم لأن هناك منتج جديد
        $this->clearAllProductsCache();

        return response()->json([
            "success" => true,
            "message" => "Product created successfully",
            "data" => $this->formatProduct($product),
        ]);
    }

    // ======================
    // 5. تحديث منتج موجود (تم إضافة مسح كاش المنتج المحدد والقوائم)
    // ======================
    public function update(Request $request, Product $product)
    {
        $request->validate([
            "name" => "sometimes|required|string|max:255",
            "slug" => "sometimes|required|string|max:255|unique:products,slug," . $product->id,
            "description" => "sometimes|nullable|string",
            "price" => "sometimes|required|numeric|min:0",
            "discount_price" => "sometimes|nullable|numeric|min:0",
            "stock" => "sometimes|required|integer|min:0",
            "sku" => "sometimes|required|string|max:255|unique:products,sku," . $product->id,
            "is_active" => "sometimes|boolean",
            "categories" => "sometimes|array|exists:categories,id",
            "image" => "sometimes|nullable|image|mimes:jpeg,png,jpg|max:2048",
        ]);

        if ($request->has('name')) {
            $product->name = $request->name;
            $product->slug = Str::slug($request->name, '-');
        }

        if ($request->hasFile('image')) {
            if ($product->image) Storage::disk('public')->delete($product->image);
            $product->image = $request->file('image')->storeAs('products', $product->slug, 'public');
        }

        $fields = ['description', 'price', 'discount_price', 'stock', 'sku', 'is_active'];
        foreach ($fields as $field) {
            if ($request->has($field)) $product->$field = $request->$field;
        }

        $product->save();

        if ($request->has('categories')) {
            $product->categories()->sync($request->categories);
        }

        $product->load('categories');

        // مسح الكاش الخاص بهذا المنتج والمنتجات العامة
        Cache::forget("product_detail_{$product->id}");
        $this->clearAllProductsCache();

        return response()->json([
            "success" => true,
            "message" => "Product updated successfully",
            "data" => $this->formatProduct($product),
        ]);
    }

    // ======================
    // 6. حذف منتج (تم إضافة مسح الكاش)
    // ======================
    public function destroy(Product $product)
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $productId = $product->id;
        $product->delete();

        // مسح الكاش
        Cache::forget("product_detail_{$productId}");
        $this->clearAllProductsCache();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    // ======================
    // 7. البحث عن المنتجات (يفضل عدم كاش البحث لأنه متغير جداً)
    // ======================
    public function search(Request $request)
    {
        $q = $request->query('q');
        if (!$q) return response()->json(['data'=>[], 'last_page'=>1]);

        $products = Product::where('name', 'like', "%{$q}%")
            ->with('categories')
            ->paginate(4);

        $products->getCollection()->transform(function ($product) {
            return $this->formatProduct($product);
        });

        return response()->json([
            "success" => true,
            "message" => "Search results",
            "data" => $products,
        ]);
    }

    // دالة مساعدة لمسح كاش القوائم (بسبب الترقيم)
    private function clearAllProductsCache()
    {
        // بما أنك تستخدم CACHE_STORE=file، أسهل طريقة لمسح كاش القوائم المرقمة 
        // دون التأثير على كاش المستخدمين أو الأقسام الأخرى هي Flush 
        // ولكن إذا أردت دقة أكثر يفضل عمل Cache::flush() حالياً للتأكد من تحديث البيانات.
        Cache::flush(); 
    }

    // ======================
    // دالة مساعدة لتنسيق المنتج (Format) - كما هي
    // ======================
    private function formatProduct(Product $product)
{
    // 1. حساب السعر النهائي (السعر الأصلي - مبلغ الخصم)
    $originalPrice = (float) $product->price;
    $discountAmount = (float) $product->discount_price;
    $finalPrice = $originalPrice - $discountAmount;

    // 2. حساب النسبة المئوية
    $discountPercentage = 0;
    if ($originalPrice > 0 && $discountAmount > 0) {
        $discountPercentage = round(($discountAmount / $originalPrice) * 100);
    }

    return [
        'id' => $product->id,
        'name' => $product->name,
        'slug' => $product->slug,
        'description' => $product->description,
        'price' => $originalPrice, // السعر الأصلي (القديم)
        
        // --- الإضافات الجديدة اللي الـ React محتاجها ---
        'final_price' => $finalPrice, // السعر اللي هيظهر لليوزر بعد الخصم
        'discount_percentage' => $discountPercentage > 0 ? $discountPercentage . '%' : null,
        'discount_amount' => $discountAmount,
        // -------------------------------------------

        'discount_price' => $discountAmount, // الحقل القديم بتاعك كما هو
        'stock' => (int)$product->stock,
        'sku' => $product->sku,
        'is_active' => (bool)$product->is_active,
        'image' => $product->image ? asset('storage/' . $product->image) : null,
        'categories' => $product->categories->map(function ($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            ];
        }),
    ];
}
}