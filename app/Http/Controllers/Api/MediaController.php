<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    /**
     * Kho ảnh bìa lớp seed sẵn. Dạng "preset:<name>" — FE render bằng gradient (không phụ thuộc
     * ảnh ngoài), cover_url lưu chuỗi này. Ảnh cô tải lên trả về URL thật từ /media/upload.
     */
    public function classCovers(): JsonResponse
    {
        $presets = ['sunset', 'ocean', 'forest', 'grape', 'candy', 'sky'];

        return response()->json([
            'data' => array_map(fn ($p) => ['id' => $p, 'cover_url' => "preset:{$p}"], $presets),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $path = $request->file('file')->store('covers', 'public');

        return response()->json(['url' => asset('storage/'.$path)]);
    }
}
