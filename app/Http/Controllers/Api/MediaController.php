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
        $type = $request->input('type', 'image');

        $request->validate([
            'file' => $type === 'audio'
                ? ['required', 'file', 'mimes:mp3,m4a,mp4,wav,ogg,oga,aac,webm,3gp,3gpp,amr,caf', 'max:20480']
                : ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $path = $type === 'audio'
            ? $request->file('file')->store('audio', 'public')
            : $request->file('file')->store('covers', 'public');

        return response()->json(['url' => asset('storage/'.$path)]);
    }

    /** Nhận diện link YouTube → trả video_id + thumbnail (nhúng không tốn dung lượng). */
    public function embedPreview(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:2048']]);
        $id = $this->youtubeId($data['url']);

        if (! $id) {
            return response()->json(['recognized' => false, 'message' => 'Không nhận diện được link YouTube.'], 422);
        }

        return response()->json([
            'recognized' => true,
            'provider' => 'youtube',
            'video_id' => $id,
            'embed_url' => "https://www.youtube-nocookie.com/embed/{$id}",
            'thumbnail' => "https://img.youtube.com/vi/{$id}/hqdefault.jpg",
        ]);
    }

    private function youtubeId(string $url): ?string
    {
        if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})#i', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
