<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    /** Học sinh chỉ xem được lộ trình lớp mình đang tham gia. */
    public function viewRoadmap(User $user, Classroom $classroom): bool
    {
        return $classroom->students()->whereKey($user->id)->exists();
    }
}
