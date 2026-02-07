<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'name.required' => 'الاسم مطلوب',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
            'password.required' => 'كلمة السر مطلوبة',
            'password.confirmed' => 'تأكيد كلمة السر غير مطابق',
        ]);
    
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'type' => 'customer', // 🔒 ثابت
        ]);
    
        $token = $user->createToken('auth_token')->plainTextToken;
    
        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->type,
            ],
            'token' => $token,
        ], 201);
    }
    
// تسجيل دخول المستخدم
public function login(Request $request)
{
    $data = $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
    ], [
        'email.required' => 'البريد الإلكتروني مطلوب',
        'email.email' => 'صيغة البريد الإلكتروني غير صحيحة',
        'password.required' => 'كلمة السر مطلوبة',
        'password.string' => 'كلمة السر يجب أن تكون نص',
    ]);

    $user = User::where('email', $data['email'])->first();

    if (!$user) {
        return response()->json(['message' => 'البريد الإلكتروني غير موجود'], 404);
    }

    if (!Hash::check($data['password'], $user->password)) {
        return response()->json(['message' => 'كلمة السر خطأ'], 401);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'تم تسجيل الدخول بنجاح',
        'token' => $token,
        'user' => [
            'id' => $user->id, // 🔥 هذا السطر هو مفتاح الحل لإصلاح خطأ undefined
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->type,
        ]
    ], 200);
}
    // تسجيل خروج المستخدم
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح'
        ], 200);
    }

    // الحصول على بيانات المستخدم الحال
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->type,
            ]
        ], 200);
    }
    public function getAccessToken(Request $request)
    {
        return response()->json([
            'token_id' => $request->user()->currentAccessToken()->id,
            'abilities' => $request->user()->currentAccessToken()->abilities,
            'created_at' => $request->user()->currentAccessToken()->created_at,
        ], 200);
    }

}