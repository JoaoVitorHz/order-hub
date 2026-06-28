<?php

namespace App\Jobs;

use App\Models\Affiliate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessOrdersPage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 3;

    public function __construct(
        private readonly array $carts,
        private readonly array $users,
        private readonly array $products,
    ) {}

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(): void
    {
        $usersMap = collect($this->users)->keyBy('id');
        $productsMap = collect($this->products)->keyBy('id');

        DB::transaction(function () use ($usersMap, $productsMap) {
            foreach ($this->carts as $cart) {
                $user = $usersMap->get($cart['userId']);
                if (!$user) {
                    continue;
                }

                $affiliate = $this->upsertAffiliate($user);
                $this->upsertProductsFromCart($cart['products'], $productsMap);
                $this->upsertOrder($cart, $affiliate->id, $productsMap);
            }
        });
    }

    private function upsertAffiliate(array $user): Affiliate
    {
        Affiliate::upsert(
            [[
                'external_id' => $user['id'],
                'name' => $user['name']['firstname'] . ' ' . $user['name']['lastname'],
                'email' => $user['email'],
                'username' => $user['username'] ?? null,
                'phone' => $user['phone'] ?? null,
                'status' => 'active',
                'address' => json_encode($user['address'] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['external_id'],
            ['name', 'email', 'username', 'phone', 'address', 'updated_at'],
        );

        return Affiliate::where('external_id', $user['id'])->first();
    }

    private function upsertProductsFromCart(array $cartProducts, \Illuminate\Support\Collection $productsMap): void
    {
        $rows = [];
        foreach ($cartProducts as $cartProduct) {
            $product = $productsMap->get($cartProduct['productId']);
            if (!$product) {
                continue;
            }
            $rows[] = [
                'external_id' => $product['id'],
                'title' => $product['title'],
                'price' => $product['price'],
                'description' => $product['description'] ?? null,
                'category' => $product['category'] ?? null,
                'image' => $product['image'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows) {
            Product::upsert($rows, ['external_id'], ['title', 'price', 'description', 'category', 'image', 'updated_at']);
        }
    }

    private function upsertOrder(array $cart, int $affiliateId, \Illuminate\Support\Collection $productsMap): void
    {
        $productIds = collect($cart['products'])->pluck('productId')->unique();
        $dbProducts = Product::whereIn('external_id', $productIds)->get()->keyBy('external_id');

        $totalValue = collect($cart['products'])->sum(function ($item) use ($productsMap) {
            $product = $productsMap->get($item['productId']);
            return $product ? $item['quantity'] * $product['price'] : 0;
        });

        $existing = Order::where('external_id', $cart['id'])->first();

        Order::upsert(
            [[
                'external_id' => $cart['id'],
                'affiliate_id' => $affiliateId,
                'status' => $existing?->status ?? 'pending',
                'total_value' => $totalValue,
                'ordered_at' => \Carbon\Carbon::parse($cart['date'] ?? now())->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['external_id'],
            ['affiliate_id', 'total_value', 'ordered_at', 'updated_at'],
        );

        $order = Order::where('external_id', $cart['id'])->first();

        // Re-sync items (delete+insert to avoid stale data)
        OrderItem::where('order_id', $order->id)->delete();

        $items = [];
        foreach ($cart['products'] as $cartProduct) {
            $dbProduct = $dbProducts->get($cartProduct['productId']);
            if (!$dbProduct) {
                continue;
            }
            $product = $productsMap->get($cartProduct['productId']);
            $items[] = [
                'order_id' => $order->id,
                'product_id' => $dbProduct->id,
                'quantity' => $cartProduct['quantity'],
                'price' => $product['price'] ?? $dbProduct->price,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($items) {
            OrderItem::insert($items);
        }

        // Log initial status if new order
        if (!$existing) {
            OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => 'pending',
                'changed_by' => 'sync',
                'changed_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessOrdersPage failed', [
            'exception' => $exception->getMessage(),
            'cart_ids' => collect($this->carts)->pluck('id')->toArray(),
        ]);
    }
}
