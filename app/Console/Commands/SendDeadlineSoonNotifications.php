<?php

namespace App\Console\Commands;

use App\Models\Mission;
use App\Notifications\DeadlineSoon;
use Illuminate\Console\Command;

class SendDeadlineSoonNotifications extends Command
{
    protected $signature = 'notifications:deadline-soon';

    protected $description = 'Nhắc học sinh các nhiệm vụ sắp đến hạn (theo notify.remind_before_hours).';

    public function handle(): int
    {
        // Tắt thông báo web → không nhắc gì cả.
        if (! setting('notify.web', true)) {
            $this->info('Thông báo web đang tắt (notify.web=false) — bỏ qua.');

            return self::SUCCESS;
        }

        $hours = (int) setting('notify.remind_before_hours', 24);

        $missions = Mission::query()
            ->whereNotNull('due_date')
            ->whereNull('deadline_notified_at')
            ->where('status', '!=', 'done')
            ->whereBetween('due_date', [now()->startOfDay(), now()->addHours($hours)])
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
