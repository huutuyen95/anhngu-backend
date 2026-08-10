<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar_url',
        'phone',
        'note',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isTeacher(): bool
    {
        return in_array($this->role, [UserRole::Teacher, UserRole::Admin], true);
    }

    public function isStudent(): bool
    {
        return $this->role === UserRole::Student;
    }

    /**
     * Loại token theo khu: học sinh dùng token "student", giáo viên/admin dùng token "teacher".
     * Dùng làm tên token (Sanctum) để phân biệt 2 loại token khi cấp phát & thu hồi.
     */
    public function tokenType(): string
    {
        return $this->isStudent() ? 'student' : 'teacher';
    }

    /**
     * Phạm vi (abilities) gắn vào token theo role. Teacher/admin là superset của student
     * (giáo viên vẫn xem được nội dung học sinh); student KHÔNG có ability 'teacher'
     * nên token học sinh không thể gọi các endpoint khu giáo viên.
     *
     * @return list<string>
     */
    public function tokenAbilities(): array
    {
        return match ($this->role) {
            UserRole::Admin => ['admin', 'teacher', 'student'],
            UserRole::Teacher => ['teacher', 'student'],
            UserRole::Student => ['student'],
        };
    }

    /**
     * Cấp một token đã gắn đúng loại + phạm vi cho role của user.
     */
    public function issueRoleToken(): \Laravel\Sanctum\NewAccessToken
    {
        return $this->createToken($this->tokenType(), $this->tokenAbilities());
    }

    /**
     * Các lớp học viên tham gia (học sinh).
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'class_user')
            ->withPivot('status')
            ->withTimestamps();
    }

    /**
     * Các lớp giáo viên đang dạy.
     */
    public function teachingClasses(): HasMany
    {
        return $this->hasMany(Classroom::class, 'teacher_id');
    }

    public function testAttempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
