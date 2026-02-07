<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class OrderStatusChanged extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        // نرسل عبر قاعدة البيانات والبث دائماً، والإيميل في حالات محددة
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تحديث طلبك #' . $this->order->order_number)
            ->line('حالة طلبك الآن هي: ' . $this->order->status->getLabel())
            ->action('عرض تفاصيل الطلب', url('/orders/' . $this->order->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'status' => $this->order->status->value,
            'message' => "تم تحديث حالة طلبك إلى: " . $this->order->status->getLabel(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'order_id' => $this->order->id,
            'status' => $this->order->status->value,
            'message' => "تحديث مباشر: حالة طلبك هي " . $this->order->status->getLabel(),
        ]);
    }
}