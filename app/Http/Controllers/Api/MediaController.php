<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\EmbedPreviewRequest;
use App\Http\Requests\Media\UploadMediaRequest;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    public function __construct(private readonly MediaService $media) {}

    public function classCovers(): JsonResponse
    {
        return response()->json(['data' => array_map(fn ($preset) => ['id' => $preset, 'cover_url' => "preset:{$preset}"], ['sunset', 'ocean', 'forest', 'grape', 'candy', 'sky'])]);
    }

    public function upload(UploadMediaRequest $request): JsonResponse
    {
        return response()->json(['url' => $this->media->upload($request->file('file'), $request->input('type', 'image'))]);
    }

    public function embedPreview(EmbedPreviewRequest $request): JsonResponse
    {
        $result = $this->media->embedPreview((string) $request->string('url'));

        return $result ? response()->json($result) : response()->json(['recognized' => false, 'message' => 'Không nhận diện được link YouTube.'], 422);
    }
}
