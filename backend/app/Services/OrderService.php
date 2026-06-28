<?php

namespace App\Services;

use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private readonly OrderRepository $repository) {}

    public function listOrders(array $filters, string $sortBy, string $sortDir): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $sortBy, $sortDir);
    }

    public function getOrder(int $id): ?Order
    {
        return $this->repository->findWithDetails($id);
    }

    public function getMetrics(): array
    {
        return Cache::remember('orders:metrics', 300, fn () => $this->repository->getMetrics());
    }

    public function updateStatus(Order $order, string $newStatus, ?string $changedBy = null): Order
    {
        if (!$order->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Transição inválida: '{$order->status}' → '{$newStatus}'. Transições permitidas: " . implode(', ', $order->getValidTransitions()) ?: 'nenhuma',
            ]);
        }

        DB::transaction(function () use ($order, $newStatus, $changedBy) {
            $previousStatus = $order->status;

            $order->update(['status' => $newStatus]);

            OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status' => $previousStatus,
                'to_status' => $newStatus,
                'changed_by' => $changedBy ?? 'system',
                'changed_at' => now(),
            ]);

            Cache::forget('orders:metrics');

            event(new OrderStatusChanged(
                orderId: $order->id,
                affiliateId: $order->affiliate_id,
                previousStatus: $previousStatus,
                newStatus: $newStatus,
                totalValue: (float) $order->total_value,
            ));
        });

        return $order->fresh(['affiliate', 'items.product', 'statusLogs']);
    }

    public function getAffiliateSummary(int $affiliateId): array
    {
        return $this->repository->getAffiliateSummary($affiliateId);
    }
}
