<?php

namespace Tests\Feature;

use App\Jobs\ProcessOrdersPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrdersSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_sync_dispatches_jobs_for_each_page(): void
    {
        Queue::fake();

        Http::fake([
            'fakestoreapi.com/users' => Http::response($this->fakeUsers(), 200),
            'fakestoreapi.com/products' => Http::response($this->fakeProducts(), 200),
            'fakestoreapi.com/carts' => Http::response($this->fakeCarts(15), 200),
        ]);

        $this->artisan('orders:sync')->assertSuccessful();

        // 15 carts / 10 per page = 2 jobs
        Queue::assertPushed(ProcessOrdersPage::class, 2);
    }

    public function test_orders_sync_handles_api_failure(): void
    {
        Queue::fake();

        Http::fake([
            'fakestoreapi.com/users' => Http::response([], 500),
            'fakestoreapi.com/products' => Http::response([], 200),
            'fakestoreapi.com/carts' => Http::response([], 200),
        ]);

        $this->artisan('orders:sync')->assertFailed();

        Queue::assertNothingPushed();
    }

    public function test_orders_sync_is_idempotent(): void
    {
        Queue::fake();

        Http::fake([
            'fakestoreapi.com/users' => Http::response($this->fakeUsers(), 200),
            'fakestoreapi.com/products' => Http::response($this->fakeProducts(), 200),
            'fakestoreapi.com/carts' => Http::response($this->fakeCarts(5), 200),
        ]);

        $this->artisan('orders:sync')->assertSuccessful();
        $this->artisan('orders:sync')->assertSuccessful();

        // Second run should also dispatch jobs — idempotency is handled by upsert in the job
        Queue::assertPushed(ProcessOrdersPage::class, 2);
    }

    private function fakeUsers(): array
    {
        return [
            ['id' => 1, 'email' => 'user1@test.com', 'username' => 'user1', 'phone' => '111', 'name' => ['firstname' => 'John', 'lastname' => 'Doe'], 'address' => []],
        ];
    }

    private function fakeProducts(): array
    {
        return [
            ['id' => 1, 'title' => 'Product 1', 'price' => 29.99, 'description' => 'Desc', 'category' => 'cat', 'image' => 'img.jpg'],
        ];
    }

    private function fakeCarts(int $count): array
    {
        return collect(range(1, $count))->map(fn ($i) => [
            'id' => $i,
            'userId' => 1,
            'date' => now()->toDateString(),
            'products' => [['productId' => 1, 'quantity' => 2]],
        ])->toArray();
    }
}
