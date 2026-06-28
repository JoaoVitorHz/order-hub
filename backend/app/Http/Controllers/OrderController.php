<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListOrdersRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service) {}

    public function index(ListOrdersRequest $request): JsonResponse
    {
        $orders = $this->service->listOrders(
            filters: $request->only(['affiliate_id', 'status', 'date_from', 'date_to', 'min_value', 'max_value']),
            sortBy: $request->input('sort_by', 'created_at'),
            sortDir: $request->input('sort_dir', 'desc'),
        );

        return response()->json([
            'data' => OrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
            'errors' => null,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $order = $this->service->getOrder($id);

        if (!$order) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => ['message' => 'Pedido não encontrado.'],
            ], 404);
        }

        return response()->json([
            'data' => new OrderResource($order),
            'meta' => null,
            'errors' => null,
        ]);
    }

    public function metrics(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->getMetrics(),
            'meta' => ['cached_at' => now()->toIso8601String()],
            'errors' => null,
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, int $id): JsonResponse
    {
        $order = $this->service->getOrder($id);

        if (!$order) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => ['message' => 'Pedido não encontrado.'],
            ], 404);
        }

        $order = $this->service->updateStatus(
            order: $order,
            newStatus: $request->input('status'),
            changedBy: $request->input('changed_by', 'api'),
        );

        return response()->json([
            'data' => new OrderResource($order),
            'meta' => null,
            'errors' => null,
        ]);
    }
}
