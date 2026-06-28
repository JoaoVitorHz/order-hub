<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 3;

    public function __construct(private readonly array $payload) {}

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(): void
    {
        $webhookUrl = config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            Log::warning('N8N webhook URL not configured, skipping notification.');
            return;
        }

        try {
            Http::timeout(10)->post($webhookUrl, $this->payload)->throw();
        } catch (\Throwable $e) {
            Log::error('Webhook POST failed, will retry', [
                'url' => $webhookUrl,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);
            // Re-throw so the queue driver handles retries; on sync driver this is caught by listener
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendWebhookNotification failed', [
            'exception' => $exception->getMessage(),
            'payload' => $this->payload,
        ]);
    }
}
