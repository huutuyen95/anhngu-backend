<?php

namespace App\Console\Commands;

use App\Models\Mission;
use App\Notifications\DeadlineSoon;
use Illuminate\Console\Command;

class SendDeadlineSoonNotifications extends Command
{
    protected $signature = 'notifications:deadline-soon';

    protected $description = 'Nhắc học sinh các nhiệm vụ sắp đến hạn (trong 2 ngày tới).';

    public function handle(): int
    {
        $missions = Mission::query()
            ->whereNotNull('due_date')
            ->whereNull('deadline_notified_at')
            ->where('status', '!=', 'done')
            ->whereBetween('due_date', [now()->startOfDay(), now()->addDays(2)->endOfDay()])
            ->with(['missionable', 'classroom:id,name', 'user'])
            ->get();

        foreach ($missions as $mission) {
            $title = $mission->missionable?->title ?? $mission->missionable?->name ?? 'Nội dung';
            $mission->user?->notify(new DeadlineSoon(
                $mission->classroom_id,
                $mission->classroom?->name ?? '',
                $title,
                $mission->due_date->format('d/m'),
                $mission->class_session_id,
            ));
            $mission->update(['deadline_notified_at' => now()]);
        }

        $this->info("Đã nhắc {$missions->count()} nhiệm vụ sắp đến hạn.");

        return self::SUCCESS;
    }
}
