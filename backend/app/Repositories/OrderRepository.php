<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderRepository
{
    public function paginate(array $filters, string $sortBy = 'created_at', string $sortDir = 'desc', int $perPage = 20): LengthAwarePaginator
    {
        $query = Order::query()
            ->with(['affiliate:id,name,email', 'items.product'])
            ->select('orders.*');

        if (!empty($filters['affiliate_id'])) {
            $query->where('affiliate_id', $filters['affiliate_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['min_value'])) {
            $query->where('total_value', '>=', $filters['min_value']);
        }

        if (!empty($filters['max_value'])) {
            $query->where('total_value', '<=', $filters['max_value']);
        }

        $allowedSorts = ['id', 'total_value', 'status', 'created_at', 'ordered_at'];
        $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'created_at';
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    public function findWithDetails(int $id): ?Order
    {
        return Order::with([
            'affiliate:id,name,email,phone',
            'items.product',
            'statusLogs',
        ])->find($id);
    }

    public function getMetrics(): array
    {
        $row = DB::selectOne("
            SELECT
                COUNT(*) AS total_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
                SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) AS refunded_orders,
                COALESCE(SUM(CASE WHEN status IN ('approved','refunded') THEN total_value ELSE 0 END), 0) AS total_revenue,
                COALESCE(AVG(CASE WHEN status IN ('approved','refunded') THEN total_value ELSE NULL END), 0) AS avg_ticket
            FROM orders
            WHERE deleted_at IS NULL
        ");

        return [
            'total_orders'     => (int) $row->total_orders,
            'pending_orders'   => (int) $row->pending_orders,
            'approved_orders'  => (int) $row->approved_orders,
            'cancelled_orders' => (int) $row->cancelled_orders,
            'refunded_orders'  => (int) $row->refunded_orders,
            'total_revenue'    => (float) $row->total_revenue,
            'avg_ticket'       => (float) $row->avg_ticket,
        ];
    }

    public function getAffiliateSummary(int $affiliateId): array
    {
        $row = DB::selectOne("
            SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(CASE WHEN status IN ('approved','refunded') THEN total_value ELSE 0 END), 0) AS total_revenue,
                COALESCE(AVG(CASE WHEN status IN ('approved','refunded') THEN total_value ELSE NULL END), 0) AS avg_ticket,
                ROUND(
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2
                ) AS cancellation_rate
            FROM orders
            WHERE affiliate_id = ? AND deleted_at IS NULL
        ", [$affiliateId]);

        return [
            'total_orders'      => (int) $row->total_orders,
            'total_revenue'     => (float) $row->total_revenue,
            'avg_ticket'        => (float) $row->avg_ticket,
            'cancellation_rate' => (float) $row->cancellation_rate,
        ];
    }

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        return Order::whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->update(['status' => $status]);
    }
}
