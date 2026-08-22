<?php

namespace App\Providers;

use App\Models\Deck;
use App\Models\Document;
use App\Models\Test;
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 1 instance/request (tự reset giữa các request/job) để memo cấu hình trong request:
        // cả request chỉ chạm Redis 1 lần thay vì mỗi lần gọi setting().
        $this->app->scoped(SettingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // API trả JSON phẳng (không bọc "data") — khớp kỳ vọng của frontend (lib/api.ts).
        JsonResource::withoutWrapping();

        // Alias ngắn cho quan hệ đa hình (session_items.itemable, missions.missionable).
        Relation::morphMap([
            'test' => Test::class,
            'deck' => Deck::class,
            'document' => Document::class,
        ]);
    }
}
