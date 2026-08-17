<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class MediaService
{
    public function upload(UploadedFile $file, string $type): string
    {
        $path = $file->store($type === 'audio' ? 'audio' : 'covers', 'public');

        return asset('storage/'.$path);
    }

    public function embedPreview(string $url): ?array
    {
        if (! preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})#i', $url, $matches)) {
            return null;
        }
        $id = $matches[1];

        return ['recognized' => true, 'provider' => 'youtube', 'video_id' => $id,
            'embed_url' => "https://www.youtube-nocookie.com/embed/{$id}", 'thumbnail' => "https://img.youtube.com/vi/{$id}/hqdefault.jpg"];
    }
}
