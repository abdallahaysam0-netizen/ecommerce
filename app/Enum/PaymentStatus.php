<?php

namespace App\Enum;

enum PaymentStatus: string
{
    case INITIATED = 'initiated';
    case PENDING   = 'pending';
    case PAID      = 'paid';
    case FAILED    = 'failed';
    case REFUNDED  = 'refunded'; // الحالة الجديدة

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
// لاحظ تم تعديل "completed" → "paid" عشان يطابق الـ frontend.

// بعد التعديلات دي:

// أي قيمة تجي من React هتتوافق مع Enum → مفيش Exception

// canTransitionTo هتشتغل بدون خطأ

// "Something went wrong while updating the order" هتختفي.

// لو تحب، أقدر أعملك نسخة Laravel كاملة من updateStatus متوافقة تماماً مع Enums الجديدة، بحيث تعمل مباشرة بدون أي مشاكل.

// هل أعملها لك؟








// لقد وصلت إلى الحد الخاص