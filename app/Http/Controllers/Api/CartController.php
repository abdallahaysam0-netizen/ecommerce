<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;

class CartController extends Controller
{
    // عرض منتجات الـ cart الخاصة بالمستخدم (مع تطبيق الخصم)
    public function index(Request $request)
    {
        $user = $request->user(); // يحصل على المستخدم من token
        $cartItems = Cart::where('user_id', $user->id)->with('product')->get();

        // --- إضافة منطق حساب الخصم هنا ---
        $cartItems->transform(function ($item) {
            if ($item->product) {
                $originalPrice = (float) $item->product->price;
                $discountAmount = (float) $item->product->discount_price;
                $finalPrice = $originalPrice - $discountAmount;

                // بنضيف البيانات المحسوبة جوه الـ product عشان الـ React يشوفها
                $item->product->final_price = $finalPrice;
                $item->product->discount_percentage = $originalPrice > 0 ? round(($discountAmount / $originalPrice) * 100) . '%' : '0%';
            }
            return $item;
        });
        // --------------------------------

        return response()->json([
            'success' => true,
            'data' => $cartItems
        ]);
    }

    // إضافة منتج للـ cart
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();

        $cartItem = Cart::updateOrCreate(
            [
                'user_id' => $user->id,
                'product_id' => $request->product_id
            ],
            [
                'quantity' => $request->quantity
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $cartItem
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $cartItem = Cart::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        return response()->json(['success' => true, 'cart' => $cartItem]);
    }

    public function destroy($id)
    {
        $cartItem = Cart::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $cartItem->delete();

        return response()->json(['success' => true]);
    }
}