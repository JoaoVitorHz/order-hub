<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Affiliate $affiliate;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->affiliate = Affiliate::create([
            'external_id' => 1,
            'name' => 'Test Affiliate',
            'email' => 'affiliate@test.com',
            'status' => 'active',
        ]);
    }

    public function test_metrics_endpoint_returns_correct_structure(): void
    {
        $response = $this->getJson('/api/orders/metrics');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_orders',
                    'pending_orders',
                    'approved_orders',
                    'cancelled_orders',
                    'refunded_orders',
                    'total_revenue',
                    'avg_ticket',
                ],
                'meta',
                'errors',
            ]);
    }

    public function test_metrics_counts_orders_correctly(): void
    {
        Order::create(['external_id' => 1, 'affiliate_id' => $this->affiliate->id, 'status' => 'pending', 'total_value' => 100, 'ordered_at' => now()]);
        Order::create(['external_id' => 2, 'affiliate_id' => $this->affiliate->id, 'status' => 'approved', 'total_value' => 200, 'ordered_at' => now()]);
        Order::create(['external_id' => 3, 'affiliate_id' => $this->affiliate->id, 'status' => 'cancelled', 'total_value' => 50, 'ordered_at' => now()]);

        Cache::forget('orders:metrics');

        $response = $this->getJson('/api/orders/metrics');

        $response->assertOk()
            ->assertJsonPath('data.total_orders', 3)
            ->assertJsonPath('data.pending_orders', 1)
            ->assertJsonPath('data.approved_orders', 1)
            ->assertJsonPath('data.cancelled_orders', 1);

        $revenue = $response->json('data.total_revenue');
        $this->assertEquals(200.0, (float) $revenue, "Expected total_revenue 200.0 but got $revenue");
    }

    public function test_metrics_are_cached(): void
    {
        Cache::forget('orders:metrics');

        $this->getJson('/api/orders/metrics');

        $this->assertTrue(Cache::has('orders:metrics'));
    }

    public function test_cache_is_invalidated_after_status_update(): void
    {
        $order = Order::create([
            'external_id' => 10,
            'affiliate_id' => $this->affiliate->id,
            'status' => 'pending',
            'total_value' => 150,
            'ordered_at' => now(),
        ]);

        // Populate cache
        $this->getJson('/api/orders/metrics');
        $this->assertTrue(Cache::has('orders:metrics'));

        // Update status should invalidate cache
        $this->postJson("/api/orders/{$order->id}/status", ['status' => 'approved']);

        $this->assertFalse(Cache::has('orders:metrics'));
    }
}
