<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // API trả JSON phẳng (không bọc "data") — khớp kỳ vọng của frontend (lib/api.ts).
        JsonResource::withoutWrapping();
    }
}
