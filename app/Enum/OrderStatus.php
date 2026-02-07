<?php

namespace App\Enum;

enum OrderStatus: string
{
    case DRAFT      = 'draft';  // طلب مبدئي قبل الدفع
    case PENDING_PAYMENT = 'pending_payment'; 
    case CONFIRMED  = 'confirmed';   // بعد الدفع
    case PROCESSING = 'processing';
    case SHIPPED    = 'shipped';
    case DELIVERED  = 'delivered';
    case COMPLETED  = 'completed';   // بعد التأكيد النهائي على التسليم
    case CANCELLED  = 'cancelled';

    // ✅ الدالة التي كانت تسبب الخطأ في الإشعارات
    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT      => 'مسودة',
            self::PENDING_PAYMENT => 'قيد الدفع',
            self::CONFIRMED  => 'تم التأكيد',
            self::PROCESSING => 'جاري التجهيز',
            self::SHIPPED    => 'تم الشحن',
            self::DELIVERED  => 'تم التوصيل',
            self::COMPLETED  => 'مكتمل',
            self::CANCELLED  => 'ملغي',
        };
    }
    // قائمة القيم كـ string
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    // حالات الانتقال المسموح بها (ترجع Enum objects)
  // app/Enum/OrderStatus.php

public function allowedTransitions(): array
{
    return match ($this) {
        // الـ Draft يمكن أن يذهب للتأكيد أو مباشرة للتجهيز (في حالة الـ COD مثلاً)
        self::DRAFT => [self::PENDING_PAYMENT, self::CONFIRMED, self::PROCESSING, self::CANCELLED],
        
        // حالة قيد الدفع يمكن أن تتأكد أو تُلغى
        self::PENDING_PAYMENT => [self::CONFIRMED, self::CANCELLED],
        
        self::CONFIRMED => [self::PROCESSING, self::CANCELLED],
        
        // جاري التجهيز يمكن أن يشحن أو يلغى (لو حدثت مشكلة في التغليف)
        self::PROCESSING => [self::SHIPPED, self::CANCELLED],
        
        self::SHIPPED => [self::DELIVERED, self::CANCELLED],
        
        self::DELIVERED => [self::COMPLETED],
        
        self::COMPLETED => [],
        self::CANCELLED => [],
    };
}
    // التحقق من إمكانية الانتقال إلى حالة أخرى
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
