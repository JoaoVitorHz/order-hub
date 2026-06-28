<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'mysql' => $this->checkMysql(),
            'redis' => $this->checkRedis(),
            'worker' => $this->checkWorker(),
        ];

        $healthy = !in_array(false, array_column($checks, 'ok'));

        return response()->json([
            'data' => [
                'status' => $healthy ? 'healthy' : 'degraded',
                'checks' => $checks,
                'timestamp' => now()->toIso8601String(),
            ],
            'meta' => null,
            'errors' => null,
        ], $healthy ? 200 : 503);
    }

    private function checkMysql(): array
    {
        try {
            DB::select('SELECT 1');
            return ['ok' => true, 'message' => 'Connected'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            Cache::store('redis')->put('health_check', 1, 5);
            return ['ok' => true, 'message' => 'Connected'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function checkWorker(): array
    {
        try {
            $size = Queue::size();
            return ['ok' => true, 'message' => "Queue size: {$size}"];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
