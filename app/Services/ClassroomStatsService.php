<?php

namespace App\Services;

use App\Models\Classroom;
use App\Repositories\ClassroomAnalyticsRepository;
use Illuminate\Support\Facades\Cache;

class ClassroomStatsService
{
    private const TTL = 900; // 15 phút

    public function __construct(private readonly ClassroomAnalyticsRepository $analytics) {}

    /**
     * Số liệu tổng hợp của 1 lớp (cache 15 phút).
     *
     * @return array<string, mixed>
     */
    public function forClass(Classroom $classroom): array
    {
        return Cache::remember("class_stats_{$classroom->id}", self::TTL, function () use ($classroom) {
            return $this->compute($classroom);
        });
    }

    public function forget(Classroom $classroom): void
    {
        Cache::forget("class_stats_{$classroom->id}");
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(Classroom $classroom): array
    {
        return $this->analytics->stats($classroom);
    }
}
