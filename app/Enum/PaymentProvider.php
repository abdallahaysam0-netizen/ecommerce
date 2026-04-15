<?php

namespace App\Enum;

enum PaymentProvider: string
{
    case PAYMOB = 'paymob';  // صحح الكتابة من 'poymob' إلى 'paymob'
    case PAYPAL = 'paypal';
    case STRIPE = 'stripe';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
