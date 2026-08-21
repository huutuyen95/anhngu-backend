<?php

namespace App\Services\Ai;

/**
 * Dựng lời nhắc gửi cho AI. Tách khỏi driver để mọi nhà cung cấp chấm theo CÙNG một bộ
 * tiêu chí và CÙNG một định dạng trả về — đổi nhà cung cấp thì điểm không nhảy lung tung.
 *
 * Bắt AI trả JSON cố định vì backend phải parse ra số điểm; phần chữ cho cô đọc thì
 * backend tự ghép ở `renderFeedback()`, không để AI tự do định dạng.
 */
final class GradingPrompt
{
    public static function system(GradingRequest $request): string
    {
        $criteria = $request->criteria();
        $lines = [];
        foreach ($criteria as $code => $label) {
            $lines[] = "- \"{$code}\": {$label}";
        }
        $criteriaList = implode("\n", $lines);
        $max = GradingRequest::AI_SCALE;

        $skill = $request->isSpeaking() ? 'bài NÓI' : 'bài VIẾT';

        $pronunciation = $request->isSpeaking()
            ? "\n- \"pronunciation\": nhận xét NGẮN về phát âm (chuỗi chữ, KHÔNG phải số). "
                .'Nghe được lỗi âm nào thì nêu cụ thể; không chắc thì ghi "không rõ".'
            : '';

        return <<<TXT
            Bạn là giáo viên tiếng Anh đang chấm {$skill} của học sinh Việt Nam (trình độ phổ thông).

            Chấm theo các tiêu chí sau, MỖI tiêu chí cho điểm từ 0 đến {$max}:
            {$criteriaList}

            Trả lời DUY NHẤT bằng một object JSON hợp lệ, không kèm chữ nào khác, theo đúng dạng:
            {
              "criteria": { "<mã tiêu chí>": <số điểm>, ... },
              "score": <điểm tổng từ 0 đến {$max}>,
              "comment": "<nhận xét chung bằng TIẾNG VIỆT, 2-4 câu, nêu rõ em làm tốt gì và cần sửa gì>",
              "errors": ["<lỗi cụ thể 1>", "<lỗi cụ thể 2>"]{$pronunciation}
            }

            Yêu cầu:
            - "score" là điểm tổng cho cả câu, KHÔNG phải tổng cộng các tiêu chí.
            - Nhận xét viết bằng tiếng Việt, giọng nhẹ nhàng, khích lệ, gọi học sinh là "em".
            - "errors" nêu tối đa 4 lỗi cụ thể, trích đúng chữ em đã dùng và gợi ý cách sửa.
            - Chấm đúng trình độ học sinh phổ thông, đừng khắt khe kiểu thi quốc tế.
            TXT;
    }

    /** 7.50 → "7.5", 8.00 → "8". */
    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * Bản cho CÔ COPY sang ChatGPT tự chấm.
     *
     * Khác bản gửi qua API ở chỗ yêu cầu trả lời bằng văn bản tiếng Việt dễ đọc thay vì JSON:
     * cô đọc bằng mắt rồi tự nhập điểm, đưa JSON cho cô đọc là làm khó cô.
     *
     * Bố cục yêu cầu trùng khít với cách khối "AI đề xuất" hiển thị, nên cô copy nguyên đoạn
     * ChatGPT trả về dán vào ô nhận xét là vừa khớp — và sau này bật chấm tự động thì hai
     * đường cũng ra cùng một dạng.
     */
    public static function forCopy(GradingRequest $request): string
    {
        $labels = array_values($request->criteria());
        $max = self::number(GradingRequest::AI_SCALE);

        $criteriaList = implode("\n", array_map(fn ($label) => "- {$label}", $labels));
        $criteriaLine = implode(' · ', array_map(fn ($label) => "{$label}: __", $labels));

        $head = <<<TXT
            Bạn là giáo viên tiếng Anh đang chấm bài VIẾT của học sinh Việt Nam (trình độ phổ thông).

            Chấm theo các tiêu chí sau, mỗi tiêu chí cho điểm từ 0 đến {$max}:
            {$criteriaList}

            Trả lời bằng TIẾNG VIỆT, theo ĐÚNG bố cục dưới đây, không thêm gì khác:

            Điểm đề xuất: __/{$max}
            Chi tiết — {$criteriaLine}

            <nhận xét chung 2-4 câu, giọng nhẹ nhàng khích lệ, gọi học sinh là "em">

            Cần sửa:
            - <lỗi cụ thể, trích đúng chữ em đã dùng và gợi ý cách sửa>

            Lưu ý: nêu tối đa 4 lỗi, chấm đúng trình độ học sinh phổ thông, đừng khắt khe kiểu thi quốc tế.
            TXT;

        return $head."\n\n".self::user($request);
    }

    /** Phần đề bài + bài làm. Với câu nói, bài làm là file audio nên không nằm ở đây. */
    public static function user(GradingRequest $request): string
    {
        $parts = ["ĐỀ BÀI:\n".trim($request->questionContent)];

        if (filled($request->hint)) {
            $parts[] = "GỢI Ý CÔ ĐƯA CHO HỌC SINH:\n".trim($request->hint);
        }

        if (filled($request->rubric)) {
            $parts[] = "TIÊU CHÍ RIÊNG CỦA CÔ (ưu tiên hơn tiêu chí mặc định):\n".trim($request->rubric);
        }

        if ($request->wordLimit) {
            $parts[] = "GIỚI HẠN: bài viết không quá {$request->wordLimit} từ.";
        }

        if ($request->isSpeaking()) {
            $parts[] = 'BÀI LÀM: là đoạn ghi âm đính kèm. Hãy nghe rồi chấm.';
        } else {
            $answer = trim((string) $request->answerText);
            $parts[] = "BÀI LÀM CỦA HỌC SINH:\n".($answer === '' ? '(em bỏ trống)' : $answer);
        }

        return implode("\n\n", $parts);
    }

    /**
     * Ghép JSON của AI thành đoạn văn bản cô dán được thẳng vào ô nhận xét.
     *
     * Cô chốt hiển thị dạng chữ ("Điểm đề xuất: …") chứ không tách cột, nên toàn bộ điểm
     * từng tiêu chí nằm gọn trong đoạn này — không phải sửa cấu trúc attempt_answers.
     *
     * @param  array<string, mixed>  $data
     */
    public static function renderFeedback(array $data, GradingRequest $request): string
    {
        $labels = $request->criteria();
        $lines = [];

        $score = isset($data['score']) ? (float) $data['score'] : null;
        if ($score !== null) {
            $line = 'Điểm đề xuất: '.self::number($score).'/'.self::number(GradingRequest::AI_SCALE);

            // Câu để thang khác 10 → nói rõ con số sẽ được điền vào ô điểm, tránh cảnh chữ
            // ghi 7.5 mà ô điểm nhảy ra 0.75 làm cô tưởng hệ thống sai.
            if (abs($request->maxScore - GradingRequest::AI_SCALE) > 0.001) {
                $line .= ' (ô điểm câu này thang '.self::number($request->maxScore)
                    .' → điền '.self::number($request->toQuestionScale($score)).')';
            }

            $lines[] = $line;
        }

        $criteria = is_array($data['criteria'] ?? null) ? $data['criteria'] : [];
        if ($criteria !== []) {
            $bits = [];
            foreach ($labels as $code => $label) {
                if (! array_key_exists($code, $criteria)) {
                    continue;
                }
                $bits[] = $label.': '.self::number((float) $criteria[$code]);
            }
            if ($bits !== []) {
                $lines[] = 'Chi tiết — '.implode(' · ', $bits);
            }
        }

        if (filled($data['comment'] ?? null)) {
            $lines[] = '';
            $lines[] = trim((string) $data['comment']);
        }

        $errors = array_filter(is_array($data['errors'] ?? null) ? $data['errors'] : []);
        if ($errors !== []) {
            $lines[] = '';
            $lines[] = 'Cần sửa:';
            foreach (array_slice($errors, 0, 4) as $error) {
                $lines[] = '- '.trim((string) $error);
            }
        }

        if ($request->isSpeaking() && filled($data['pronunciation'] ?? null)) {
            $lines[] = '';
            $lines[] = 'Phát âm: '.trim((string) $data['pronunciation']);
            $lines[] = '('.config('ai.pronunciation_note').')';
        }

        return trim(implode("\n", $lines));
    }
}
