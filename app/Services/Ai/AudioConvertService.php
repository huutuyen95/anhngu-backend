<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Chuyển bản ghi của học viên sang định dạng API nhận được.
 *
 * Học viên ghi bằng đủ loại trình duyệt: Chrome/Android ra `webm`, Safari iPhone/iPad ra
 * `mp4`/`m4a`, Firefox ra `ogg`. OpenAI chỉ nhận `mp3` và `wav` → phải chuyển bằng ffmpeg.
 *
 * File đã sẵn mp3/wav thì dùng luôn, không đụng ffmpeg — nghĩa là ngay cả khi container
 * chưa cài ffmpeg, bài học viên tự tải lên dạng .mp3/.wav vẫn chấm được.
 */
final class AudioConvertService
{
    /** Định dạng API nhận trực tiếp, không cần chuyển. */
    private const PASSTHROUGH = ['mp3', 'wav'];

    /** ffmpeg có sẵn trong container không (cache trong 1 request). */
    private ?bool $ffmpegAvailable = null;

    public function available(): bool
    {
        if ($this->ffmpegAvailable !== null) {
            return $this->ffmpegAvailable;
        }

        $probe = new Process(['which', 'ffmpeg']);
        $probe->run();

        return $this->ffmpegAvailable = $probe->isSuccessful();
    }

    /**
     * Trả về [đường dẫn file cục bộ, định dạng, cần-xoá-sau-khi-dùng].
     *
     * @return array{0: string, 1: string, 2: bool}|null `null` khi không chuyển được
     */
    public function prepare(string $publicUrl): ?array
    {
        $relative = $this->relativePath($publicUrl);

        if (! $relative || ! Storage::disk('public')->exists($relative)) {
            return null;
        }

        $source = Storage::disk('public')->path($relative);
        $extension = Str::lower(pathinfo($relative, PATHINFO_EXTENSION));

        if (in_array($extension, self::PASSTHROUGH, true)) {
            return [$source, $extension, false];
        }

        if (! $this->available()) {
            return null;
        }

        $target = rtrim(sys_get_temp_dir(), '/').'/ai-grade-'.Str::random(16).'.mp3';

        $process = new Process([
            'ffmpeg', '-y',
            '-i', $source,
            // Mono 16kHz đủ cho giọng nói và giảm mạnh dung lượng gửi đi.
            '-ac', '1', '-ar', '16000', '-b:a', '48k',
            $target,
        ]);
        $process->setTimeout(120);

        try {
            $process->mustRun();
        } catch (ProcessFailedException) {
            @unlink($target);

            return null;
        }

        return [$target, 'mp3', true];
    }

    /** Xoá file tạm do `prepare()` tạo ra. */
    public function cleanup(string $path, bool $temporary): void
    {
        if ($temporary && is_file($path)) {
            @unlink($path);
        }
    }

    /** `answer_file_url` lưu URL công khai — đổi ngược về đường dẫn trên disk. */
    private function relativePath(string $url): ?string
    {
        $prefix = asset('storage/');

        if (str_starts_with($url, $prefix)) {
            return substr($url, strlen($prefix));
        }

        // Dự phòng khi APP_URL đổi sau khi file đã lưu.
        $position = strpos($url, '/storage/');

        return $position === false ? null : substr($url, $position + strlen('/storage/'));
    }
}
