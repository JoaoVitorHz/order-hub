<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class AffiliateController extends Controller
{
    public function __construct(private readonly OrderService $service) {}

    public function summary(int $id): JsonResponse
    {
        $affiliate = Affiliate::find($id);

        if (!$affiliate) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => ['message' => 'Afiliado não encontrado.'],
            ], 404);
        }

        $summary = $this->service->getAffiliateSummary($id);

        return response()->json([
            'data' => [
                'affiliate' => [
                    'id' => $affiliate->id,
                    'name' => $affiliate->name,
                    'email' => $affiliate->email,
                ],
                'summary' => [
                    'total_orders' => (int) ($summary['total_orders'] ?? 0),
                    'total_revenue' => (float) ($summary['total_revenue'] ?? 0),
                    'avg_ticket' => (float) ($summary['avg_ticket'] ?? 0),
                    'cancellation_rate' => (float) ($summary['cancellation_rate'] ?? 0),
                ],
            ],
            'meta' => null,
            'errors' => null,
        ]);
    }
}
