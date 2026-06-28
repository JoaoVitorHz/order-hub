<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly int $affiliateId,
        public readonly string $previousStatus,
        public readonly string $newStatus,
        public readonly float $totalValue,
    ) {}
}
