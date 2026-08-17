<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Models\SettingChange;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SettingRepository
{
    public function values(): Collection
    {
        return Setting::pluck('value', 'key');
    }

    public function lastUpdatedAt(): mixed
    {
        return Setting::max('updated_at');
    }

    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function delete(string $key): void
    {
        Setting::where('key', $key)->delete();
    }

    public function upsert(string $key, array $data): void
    {
        Setting::updateOrCreate(['key' => $key], $data);
    }

    public function logChange(array $data): void
    {
        SettingChange::create($data);
    }

    public function changes(int $perPage = 20): LengthAwarePaginator
    {
        return SettingChange::with('changedBy:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
