<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOrdersPage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class OrdersSync extends Command
{
    protected $signature = 'orders:sync';
    protected $description = 'Sync orders from FakeStore API asynchronously';

    private const API_BASE = 'https://fakestoreapi.com';
    private const RATE_KEY = 'fakestoreapi';
    private const RATE_LIMIT = 5;   // max 5 requests per second
    private const RATE_WINDOW = 1;

    public function handle(): int
    {
        $this->info('Fetching data from FakeStore API...');

        $users = $this->fetchWithRateLimit('/users');
        $products = $this->fetchWithRateLimit('/products');
        $carts = $this->fetchWithRateLimit('/carts');

        if (!$users || !$products || !$carts) {
            $this->error('Failed to fetch data from FakeStore API.');
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Fetched %d users, %d products, %d carts.',
            count($users),
            count($products),
            count($carts),
        ));

        // Dispatch one Job per page of 10 carts
        $chunks = array_chunk($carts, 10);

        foreach ($chunks as $index => $chunk) {
            ProcessOrdersPage::dispatch($chunk, $users, $products);
            $this->info(sprintf('Dispatched job for page %d/%d (%d carts)', $index + 1, count($chunks), count($chunk)));
        }

        $this->info(sprintf('Dispatched %d job(s). Use `queue:work` to process them.', count($chunks)));

        return self::SUCCESS;
    }

    private function fetchWithRateLimit(string $path): ?array
    {
        // Allow at most RATE_LIMIT requests per RATE_WINDOW seconds
        RateLimiter::attempt(
            self::RATE_KEY,
            self::RATE_LIMIT,
            fn () => true,
            self::RATE_WINDOW,
        );

        $remaining = RateLimiter::remaining(self::RATE_KEY, self::RATE_LIMIT);
        if ($remaining === 0) {
            $this->warn('Rate limit hit, sleeping 1s...');
            sleep(1);
        }

        $response = Http::timeout(15)->get(self::API_BASE . $path);

        if ($response->failed()) {
            $this->error("Failed to fetch {$path}: " . $response->status());
            return null;
        }

        return $response->json();
    }
}
