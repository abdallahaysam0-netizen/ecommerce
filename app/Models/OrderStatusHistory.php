<?php

namespace App\Models;

use App\Enum\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
protected $fillable = [
    'order_id',
    'from_status',
    'to_status',
    'user_id',
    'notes',
];
protected $casts = [
    'from_status'=>OrderStatus::class,
    'to_status'=>OrderStatus::class,
];
// 🎯 لماذا نستخدم الـ casts مع Enum؟

// لترتيب الكود.

// ولتجنب الأخطاء الناتجة عن كتابة النصوص بشكل خاطئ.

// وللحصول على IntelliSense في الـ IDE.

// وللتحقق من أن القيمة دائمًا واحدة من القيم المسموح بها داخل الـ Enum.
public function order(){
    return $this->belongsTo(Order::class);
}
public function changeBy(){
    return $this->belongsTo(User::class);
}
}
