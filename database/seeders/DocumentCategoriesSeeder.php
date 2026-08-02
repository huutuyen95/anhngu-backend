<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use Illuminate\Database\Seeder;

class DocumentCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $cats = ['Grammar', 'Vocabulary', 'Skills', 'Ôn tập', 'Chưa phân loại'];
        foreach ($cats as $i => $name) {
            DocumentCategory::firstOrCreate(['name' => $name], ['order' => $i + 1]);
        }
    }
}
