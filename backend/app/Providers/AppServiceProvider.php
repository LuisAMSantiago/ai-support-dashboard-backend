<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\AiTicketServiceInterface;
use App\Services\MockAiTicketService;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Arr;

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
        // Register response macros to standardize API responses
        Response::macro('apiSuccess', function ($data = null, $meta = []) {
            $baseMeta = [
                'success' => true,
                'code' => 200,
            ];
            $finalMeta = array_merge($baseMeta, (array) $meta);
            return response()->json(['data' => $data, 'meta' => $finalMeta], $finalMeta['code']);
        });

        Response::macro('apiError', function ($message = 'Error', $code = 500, $extra = []) {
            $meta = array_merge([
                'success' => false,
                'message' => $message,
                'code' => $code,
            ], (array) $extra);
            return response()->json(['data' => null, 'meta' => $meta], $code);
        });
    }
}
