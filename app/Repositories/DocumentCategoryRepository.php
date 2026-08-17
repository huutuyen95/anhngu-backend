<?php

namespace App\Repositories;

use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DocumentCategoryRepository
{
    public function allWithCounts(): Collection
    {
        return DocumentCategory::query()->withCount('documents')->orderBy('order')->get()
            ->map(fn (DocumentCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'order' => $category->order,
                'documents_count' => $category->documents_count,
            ]);
    }

    public function sync(array $categories, array $deletedIds): int
    {
        return DB::transaction(function () use ($categories, $deletedIds) {
            $movedCount = 0;
            if ($deletedIds !== []) {
                $fallback = DocumentCategory::firstOrCreate(['name' => 'Chưa phân loại'], ['order' => 999]);
                $movedCount = Document::whereIn('category_id', $deletedIds)->update(['category_id' => $fallback->id]);
                DocumentCategory::whereIn('id', $deletedIds)->whereKeyNot($fallback->id)->delete();
            }
            foreach ($categories as $category) {
                $values = ['name' => $category['name'], 'order' => $category['order']];
                if ($category['id'] !== null) {
                    DocumentCategory::whereKey($category['id'])->update($values);
                } else {
                    DocumentCategory::create($values);
                }
            }

            return $movedCount;
        });
    }
}
