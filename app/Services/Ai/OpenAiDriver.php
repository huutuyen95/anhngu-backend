<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Chấm bài qua OpenAI (Chat Completions).
 *
 * Câu viết → gửi chữ. Câu nói → gửi THẲNG file ghi âm cho model đa phương thức, model nghe
 * được nên nhận xét được cả độ trôi chảy và phát âm (thứ mà bản chữ làm mất). API chỉ nhận
 * `mp3`/`wav`, nên file phải được AudioConvertService chuyển trước.
 */
final class OpenAiDriver implements GradingDriver
{
    public function __construct(private readonly AiConfig $config) {}

    public function name(): string
    {
        return 'openai';
    }

    public function isReady(): bool
    {
        return filled($this->config->apiKey());
    }

    public function supportsAudio(): bool
    {
        return true;
    }

    public function grade(GradingRequest $request): GradingResult
    {
        $model = $request->isSpeaking() ? $this->config->audioModel() : $this->config->textModel();

        $response = Http::withToken((string) $this->config->apiKey())
            ->timeout((int) config('ai.openai.timeout', 120))
            ->acceptJson()
            ->post(rtrim((string) config('ai.openai.base_url'), '/').'/chat/completions', [
                'model' => $model,
                // Ép JSON để parse được; không có cờ này model hay chèn thêm lời dẫn.
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => GradingPrompt::system($request)],
                    ['role' => 'user', 'content' => $this->userContent($request)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'OpenAI trả lỗi '.$response->status().': '.$response->json('error.message', $response->body())
            );
        }

        $body = $response->json();
        $text = (string) data_get($body, 'choices.0.message.content', '');
        $data = json_decode($text, true);

        if (! is_array($data)) {
            throw new RuntimeException('Không đọc được JSON từ phản hồi của AI.');
        }

        $criteria = [];
        foreach ((array) ($data['criteria'] ?? []) as $code => $value) {
            if (is_numeric($value)) {
                $criteria[(string) $code] = (float) $value;
            }
        }

        return new GradingResult(
            // AI chấm thang 10 → quy về thang riêng của câu (và kẹp trong khoảng hợp lệ).
            score: $request->toQuestionScale((float) ($data['score'] ?? 0)),
            feedback: GradingPrompt::renderFeedback($data, $request),
            criteria: $criteria,
            model: $model,
            inputTokens: (int) data_get($body, 'usage.prompt_tokens', 0),
            outputTokens: (int) data_get($body, 'usage.completion_tokens', 0),
            audioInputTokens: (int) data_get($body, 'usage.prompt_tokens_details.audio_tokens', 0),
            raw: $text,
        );
    }

    /**
     * Câu viết gửi chuỗi thường; câu nói gửi mảng gồm phần chữ + phần audio base64.
     *
     * @return string|array<int, array<string, mixed>>
     */
    private function userContent(GradingRequest $request): string|array
    {
        $text = GradingPrompt::user($request);

        if (! $request->isSpeaking()) {
            return $text;
        }

        if (! $request->audioPath || ! is_readable($request->audioPath)) {
            throw new RuntimeException('Không đọc được file ghi âm của học viên.');
        }

        return [
            ['type' => 'text', 'text' => $text],
            [
                'type' => 'input_audio',
                'input_audio' => [
                    'data' => base64_encode((string) file_get_contents($request->audioPath)),
                    'format' => $request->audioFormat ?? 'mp3',
                ],
            ],
        ];
    }
}
