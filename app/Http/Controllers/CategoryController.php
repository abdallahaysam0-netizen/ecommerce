<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache; // استدعاء الكاش

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // استخدام Cache::remember لتخزين القائمة الكاملة
        $categories = Cache::remember('categories_all', 3600, function () {
            return Category::with('products')->get();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Categories retrieved successfully',
            'data' => $categories,
        ]);
    }

    public function products($id) 
    {
        // كاش لمنتجات كل قسم على حدة
        $cacheKey = "category_products_{$id}";

        $products = Cache::remember($cacheKey, 3600, function () use ($id) {
            $category = Category::with('products')->findOrFail($id);
            return $category->products;
        });

        return response()->json(['data' => $products]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            "name" => "required|string|max:255",
            "slug" => "nullable|string|max:255|unique:categories",
            "description" => "nullable|string",
            "parent_id" => "nullable|exists:categories,id",
            "is_active" => 'boolean',
        ]);

        $slug = Str::slug($request->name, '-');
        $count = Category::where('slug', $slug)->count();
        if ($count > 0) {
            $slug = $slug . '-' . ($count + 1);
        }

        $category = Category::create([
            "name" => $request->name,
            "slug" => $slug,
            "description" => $request->description,
            "parent_id" => $request->parent_id,
            "is_active" => $request->is_active ?? true,
        ]);

        // مسح كاش القائمة العامة عند إضافة قسم جديد
        Cache::forget('categories_all');

        return response()->json([
            'status' => 'success',
            'message' => 'categories created successfully',
            'data' => $category,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // كاش لتفاصيل القسم الواحد
        $cacheKey = "category_show_{$id}";

        $category = Cache::remember($cacheKey, 3600, function () use ($id) {
            $cat = Category::findOrFail($id);
            $cat->load(['parent', 'children']);
            return $cat;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'categories retrieved successfully',
            'data' => $category,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $request->validate([
            "name" => "sometimes|required|string|max:255",
            "description" => "nullable|string",
            "parent_id" => "nullable|exists:categories,id",
            "is_active" => 'sometimes|boolean',
        ]);

        $category->name = $request->name ?? $category->name;
        if ($request->name) {
            $category->slug = Str::slug($request->name);
            $count = Category::where('slug', $category->slug)->where('id', '!=', $category->id)->count();
            if ($count > 0) {
                $category->slug .= '-' . ($count + 1);
            }
        }

        $category->parent_id = $request->parent_id ?? null;
        $category->description = $request->description ?? $category->description;

        if ($request->has('is_active')) {
            $category->is_active = (bool)$request->is_active;
        }

        $category->save();

        // مسح كل ملفات الكاش المتعلقة بهذا القسم وبالقائمة العامة
        Cache::forget('categories_all');
        Cache::forget("category_show_{$category->id}");
        Cache::forget("category_products_{$category->id}");

        return response()->json([
            'status' => 'success',
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        foreach ($category->children as $child) {
            $child->parent_id = $category->parent_id;
            $child->save();
        }

        $categoryId = $category->id; // حفظ الـ ID قبل الحذف لمسح الكاش
        $category->delete();

        // مسح ملفات الكاش
        Cache::forget('categories_all');
        Cache::forget("category_show_{$categoryId}");
        Cache::forget("category_products_{$categoryId}");

        return response()->json([
            'status' => 'success',
            'message' => 'Category deleted successfully',
        ]);
    }
}