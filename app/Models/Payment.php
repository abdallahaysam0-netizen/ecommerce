<?php

namespace App\Models;

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;


class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'provider',
        'payment_intent_id',
        'amount',
        'currency',
        'status',
        'metadata',
        'completed_at',

    ];
    protected $casts = [
        'metadata' => 'array',
        'completed_at' => 'datetime',
        'amount' => 'decimal:2',
       'provider' => PaymentProvider::class,
'status' => PaymentStatus::class,
    ];
public function order()
{
    return $this->belongsTo(Order::class);
}
public function user()
{
    return $this->belongsTo(User::class);
}
    public function markAsCompleted($paymentIntentId, $metadata)
    {
        $this->update([
            'status' => PaymentStatus::PAID,
            'payment_intent_id' => $paymentIntentId,
            'completed_at' => now(),
            'metadata' => array_merge($this->$metadata ?? [], $metadata),
        ]);
        $this->order->markAsPaid($paymentIntentId);
    }
    public function markAsFailed($metadata = [])
    {
        $this->update([
            'status' => PaymentStatus::FAILED,
            'metadata' => array_merge($this->$metadata ?? [], $metadata),
        ]);
        $this->order->markAsFailed();
    }
    public function isFinal()
    {
        return in_array($this->status, [
            PaymentStatus::PENDING,
            PaymentStatus::FAILED,
            PaymentStatus::PAID,
        ]);
    }
}
