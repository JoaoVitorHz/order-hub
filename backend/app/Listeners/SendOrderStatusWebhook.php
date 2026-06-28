<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Jobs\SendWebhookNotification;

class SendOrderStatusWebhook
{
    public function handle(OrderStatusChanged $event): void
    {
        try {
            SendWebhookNotification::dispatch([
                'event' => 'order.status_changed',
                'order_id' => $event->orderId,
                'affiliate_id' => $event->affiliateId,
                'previous_status' => $event->previousStatus,
                'new_status' => $event->newStatus,
                'total_value' => $event->totalValue,
                'occurred_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            // Webhook failures must not impact the main order flow
            \Illuminate\Support\Facades\Log::warning('Failed to dispatch webhook job', [
                'exception' => $e->getMessage(),
                'order_id' => $event->orderId,
            ]);
        }
    }
}
