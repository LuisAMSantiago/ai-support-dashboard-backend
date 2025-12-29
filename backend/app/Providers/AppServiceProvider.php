<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\AiTicketServiceInterface;
use App\Services\MockAiTicketService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiTicketServiceInterface::class, MockAiTicketService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
