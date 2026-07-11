<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $teacher = User::create([
            'name' => 'Cô giáo',
            'email' => 'teacher@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Teacher,
        ]);

        $student = User::create([
            'name' => 'Học sinh',
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Student,
        ]);

        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'name' => 'Lớp 12F',
            'slug' => 'lop-12f',
            'is_active' => true,
        ]);

        $classroom->students()->attach($student->id, ['status' => 'studying']);

        $vehicles = Deck::create([
            'owner_id' => $teacher->id,
            'name' => 'Phương tiện',
            'slug' => 'phuong-tien',
            'is_public' => true,
        ]);

        $vehicles->cards()->createMany([
            ['order' => 1, 'term' => 'car', 'meaning' => 'ô tô', 'ipa' => '/kɑːr/'],
            ['order' => 2, 'term' => 'train', 'meaning' => 'tàu hỏa', 'ipa' => '/treɪn/'],
            ['order' => 3, 'term' => 'bus', 'meaning' => 'xe buýt', 'ipa' => '/bʌs/'],
        ]);

        $animals = Deck::create([
            'owner_id' => $teacher->id,
            'name' => 'Động vật',
            'slug' => 'dong-vat',
            'is_public' => true,
        ]);

        $animals->cards()->createMany([
            ['order' => 1, 'term' => 'dog', 'meaning' => 'con chó', 'ipa' => '/dɒg/'],
            ['order' => 2, 'term' => 'cat', 'meaning' => 'con mèo', 'ipa' => '/kæt/'],
            ['order' => 3, 'term' => 'bird', 'meaning' => 'con chim', 'ipa' => '/bɜːrd/'],
        ]);

        $colors = Deck::create([
            'owner_id' => $teacher->id,
            'name' => 'Màu sắc',
            'slug' => 'mau-sac',
            'is_public' => true,
        ]);

        $colors->cards()->createMany([
            ['order' => 1, 'term' => 'red', 'meaning' => 'màu đỏ', 'ipa' => '/red/'],
            ['order' => 2, 'term' => 'blue', 'meaning' => 'màu xanh dương', 'ipa' => '/bluː/'],
            ['order' => 3, 'term' => 'green', 'meaning' => 'màu xanh lá', 'ipa' => '/griːn/'],
        ]);
    }
}
