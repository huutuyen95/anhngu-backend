<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $cats = DocumentCategory::withCount('documents')->orderBy('order')->get()
            ->map(fn (DocumentCategory $c) => ['id' => $c->id, 'name' => $c->name, 'order' => $c->order, 'documents_count' => $c->documents_count]);

        return response()->json(['data' => $cats]);
    }

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categories' => ['present', 'array'],
            'categories.*.id' => ['nullable', 'integer'],
            'categories.*.name' => ['required', 'string', 'max:100'],
            'categories.*.order' => ['nullable', 'integer'],
            'deleted_ids' => ['array'],
            'deleted_ids.*' => ['integer'],
        ]);

        $movedCount = 0;

        DB::transaction(function () use ($data, &$movedCount) {
            $deleted = $data['deleted_ids'] ?? [];
            if ($deleted) {
                // Dồn nội dung của danh mục bị xoá về "Chưa phân loại".
                $fallback = DocumentCategory::firstOrCreate(['name' => 'Chưa phân loại'], ['order' => 999]);
                $movedCount = Document::whereIn('category_id', $deleted)->update(['category_id' => $fallback->id]);
                DocumentCategory::whereIn('id', $deleted)->where('id', '!=', $fallback->id)->delete();
            }

            foreach ($data['categories'] as $i => $cat) {
                $order = $cat['order'] ?? $i + 1;
                if (! empty($cat['id'])) {
                    DocumentCategory::where('id', $cat['id'])->update(['name' => $cat['name'], 'order' => $order]);
                } else {
                    DocumentCategory::create(['name' => $cat['name'], 'order' => $order]);
                }
            }
        });

        $cats = DocumentCategory::withCount('documents')->orderBy('order')->get()
            ->map(fn (DocumentCategory $c) => ['id' => $c->id, 'name' => $c->name, 'order' => $c->order, 'documents_count' => $c->documents_count]);

        return response()->json(['data' => $cats, 'moved_count' => $movedCount]);
    }
}
