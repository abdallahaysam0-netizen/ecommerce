<?php

namespace App\Models;

use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'paymob_id', 'status', 'shipping_name', 'shipping_address',
        'shipping_city', 'shipping_state', 'shipping_zipcode', 'shipping_country',
        'shipping_phone', 'subtotal', 'tax', 'shipping_cost', 'total',
        'payment_method', 'payment_status', 'paid_at', 'order_number', 'notes',
        'transaction_id',
    ];

    protected function casts(): array 
    {
        return [
            'status'         => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'paid_at'        => 'datetime',
        ];
    }

    public static function generateOrderNumber(): string
    {
        return 'ORD-' . strtoupper(uniqid());
    }

    // 🔹 العلاقات (Relations)
    public function user(): BelongsTo 
    { 
        return $this->belongsTo(User::class); 
    }

    public function items(): HasMany 
    { 
        return $this->hasMany(OrderItem::class); 
    }

    // 🔹 المنطق البرمجي (Business Logic)
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            OrderStatus::DRAFT, 
            OrderStatus::CONFIRMED,
            OrderStatus::PROCESSING 
        ], true);
    }

    public function getAllowedTransitions(): array
    {
        return $this->status->allowedTransitions();
    }

    public function markAsPaid(string $transactionId): void
    {
        $this->update([
            'status'         => OrderStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
            'transaction_id' => $transactionId,
            'paid_at'        => now(),
        ]);
    }

    public function markAsFailed(?string $transactionId = null): void
    {
        $this->update([
            'payment_status' => PaymentStatus::FAILED,
            'transaction_id' => $transactionId ?? $this->transaction_id,
        ]);
    }

    public function canAcceptPayment(): bool
    {
        return in_array($this->payment_status, [PaymentStatus::PENDING, PaymentStatus::FAILED], true);
    }
}