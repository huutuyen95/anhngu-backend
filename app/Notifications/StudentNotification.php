<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Thông báo lưu vào DB (channel 'database') cho học sinh. */
abstract class StudentNotification extends Notification
{
    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string,mixed> */
    abstract public function toArray(object $notifiable): array;
}
