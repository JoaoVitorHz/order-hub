<?php

namespace App\Providers;

use App\Events\OrderStatusChanged;
use App\Listeners\SendOrderStatusWebhook;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(OrderStatusChanged::class, SendOrderStatusWebhook::class);
    }
}
