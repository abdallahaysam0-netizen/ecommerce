<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $previousStatus = null,
        public ?string $changedBy = null,
    ) {
        // تحميل العلاقات لضمان وجود بيانات المستخدم والمنتجات
        $this->order->load(['user', 'items.product']);
    }

    public function broadcastOn(): array
    {
        return [
            // 💡 تصحيح: يجب أن يطابق الاسم الموجود في React و channels.php
            new PrivateChannel('App.Models.User.' . $this->order->user_id),
            new PrivateChannel('admin.orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }

    // 💡 تصحيح: حرف W كبير لكي يتعرف عليها المحرك
    public function broadcastWith(): array
    {
        $broadcastData = [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'current_status' => $this->order->status->value,
            'current_status_label' => $this->order->status->getLabel(),
            'previousStatus' => $this->previousStatus,
            'changedby' => $this->changedBy,
            'total' => $this->order->total,
            'updated' => $this->order->updated_at->toISOString(),
            'user' => [
                'id' => $this->order->user->id,
                'name' => $this->order->user->name,
            ],
            'items_count' => $this->order->items->count(),
            'items_summary' => $this->order->items->take(3)->map(fn($item) => [
                'product_name' => $item->product->name,
                'quantity' => $item->quantity,
            ])->toArray(),
        ];

        Log::info('✅ تم إرسال البث لطلب رقم: ' . $this->order->order_number);
        
        return $broadcastData;
    }
}