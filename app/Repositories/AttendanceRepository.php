<?php

namespace App\Repositories;

use App\Models\ClassSession;
use App\Models\SessionAttendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AttendanceRepository
{
    public function students(ClassSession $s)
    {
        return $s->classroom->students()->orderBy('name')->get();
    }

    public function records(ClassSession $s)
    {
        return SessionAttendance::where('class_session_id', $s->id)->get()->keyBy('user_id');
    }

    public function upsert(ClassSession $s, array $items, User $teacher): int
    {
        return DB::transaction(function () use ($s, $items, $teacher) {
            $n = 0;
            foreach ($items as $i) {
                SessionAttendance::updateOrCreate(['class_session_id' => $s->id, 'user_id' => $i['user_id']], ['status' => $i['status'], 'comment' => $i['comment'] ?? null, 'updated_by' => $teacher->id]);
                $n++;
            }

            return $n;
        });
    }
}
