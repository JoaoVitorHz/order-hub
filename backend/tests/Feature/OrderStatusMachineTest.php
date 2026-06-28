<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusMachineTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $affiliate = Affiliate::create([
            'external_id' => 1,
            'name' => 'Test Affiliate',
            'email' => 'affiliate@test.com',
            'status' => 'active',
        ]);

        $this->order = Order::create([
            'external_id' => 100,
            'affiliate_id' => $affiliate->id,
            'status' => 'pending',
            'total_value' => 99.90,
            'ordered_at' => now()->toDateString(),
        ]);
    }

    public function test_pending_can_transition_to_approved(): void
    {
        $response = $this->postJson("/api/orders/{$this->order->id}/status", [
            'status' => 'approved',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('orders', ['id' => $this->order->id, 'status' => 'approved']);
        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $this->order->id,
            'from_status' => 'pending',
            'to_status' => 'approved',
        ]);
    }

    public function test_pending_can_transition_to_cancelled(): void
    {
        $response = $this->postJson("/api/orders/{$this->order->id}/status", [
            'status' => 'cancelled',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_pending_cannot_transition_to_refunded(): void
    {
        $response = $this->postJson("/api/orders/{$this->order->id}/status", [
            'status' => 'refunded',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.status.0', fn ($v) => str_contains($v, 'Transição inválida'));
    }

    public function test_approved_can_transition_to_refunded(): void
    {
        $this->order->update(['status' => 'approved']);

        $response = $this->postJson("/api/orders/{$this->order->id}/status", [
            'status' => 'refunded',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'refunded');
    }

    public function test_approved_cannot_transition_to_cancelled(): void
    {
        $this->order->update(['status' => 'approved']);

        $response = $this->postJson("/api/orders/{$this->order->id}/status", [
            'status' => 'cancelled',
        ]);

        $response->assertUnprocessable();
    }

    public function test_cancelled_has_no_valid_transitions(): void
    {
        $this->order->update(['status' => 'cancelled']);

        foreach (['approved', 'refunded', 'pending'] as $status) {
            $response = $this->postJson("/api/orders/{$this->order->id}/status", [
                'status' => $status,
            ]);
            $response->assertUnprocessable();
        }
    }

    public function test_refunded_has_no_valid_transitions(): void
    {
        $this->order->update(['status' => 'refunded']);

        foreach (['approved', 'cancelled', 'pending'] as $status) {
            $response = $this->postJson("/api/orders/{$this->order->id}/status", [
                'status' => $status,
            ]);
            $response->assertUnprocessable();
        }
    }

    public function test_invalid_status_value_returns_422(): void
    {
        $response = $this->postJson("/api/orders/{$this->order->id}/status", [
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.status.0', fn ($v) => $v !== null);
    }
}
