<?php

/*
|--------------------------------------------------------------------------
| Chấm bài bằng AI
|--------------------------------------------------------------------------
| Cấu hình cô đổi được (bật/tắt, khoá API, model, hạn mức) nằm ở khu Cài đặt —
| xem nhóm `ai` trong config/appsettings.php. File này chỉ giữ những thứ thuộc về
| kỹ thuật: bảng giá để ước tính chi phí, và tiêu chí chấm.
|
| Khoá API đọc từ Cài đặt trước, không có thì mới lấy env — để cô tự dán khoá ở
| màn Cài đặt mà không cần ai sửa file trên server.
*/

return [

    'openai' => [
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        // Dự phòng khi cô chưa dán khoá vào Cài đặt.
        'api_key' => env('OPENAI_API_KEY'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 120),
    ],

    /*
    | Bảng giá USD / 1 triệu token, dùng để ƯỚC TÍNH chi phí hiển thị cho cô.
    | Hoá đơn thật vẫn là của nhà cung cấp — bảng này chỉ để cảnh báo sớm khi
    | sắp chạm hạn mức. Giá đổi thì sửa ở đây, không phải sửa code.
    */
    'pricing' => [
        'gpt-5.4-mini' => ['input' => 0.75, 'output' => 4.50],
        'gpt-5.6-luna' => ['input' => 0.20, 'output' => 1.20],
        'gpt-5.4' => ['input' => 2.50, 'output' => 15.00],
        // Model nghe được audio: token âm thanh tính riêng, đắt hơn token chữ nhiều.
        'gpt-audio' => ['input' => 2.50, 'output' => 10.00, 'audio_input' => 32.00],
    ],

    /** Giá mặc định khi model cô chọn không có trong bảng trên. */
    'fallback_pricing' => ['input' => 2.50, 'output' => 10.00, 'audio_input' => 32.00],

    /** Audio quy đổi khoảng 600 token cho mỗi phút học viên nói. */
    'audio_tokens_per_minute' => 600,

    /*
    | Tiêu chí chấm. AI trả điểm từng tiêu chí, backend ghép thành đoạn văn bản
    | "Điểm đề xuất: …" đưa vào ô nhận xét — không thêm cột nào vào attempt_answers.
    */
    'criteria' => [
        'writing' => [
            'task' => 'Trả lời đúng yêu cầu đề bài',
            'vocabulary' => 'Từ vựng',
            'grammar' => 'Ngữ pháp',
            'coherence' => 'Bố cục & mạch lạc',
        ],
        'speaking' => [
            'task' => 'Trả lời đúng yêu cầu đề bài',
            'vocabulary' => 'Từ vựng',
            'grammar' => 'Ngữ pháp',
            'fluency' => 'Độ trôi chảy',
        ],
    ],

    /*
    | Phát âm: model nghe được audio nên nhận xét được, NHƯNG nó không phải máy chấm
    | phát âm chuyên dụng (không có điểm từng âm vị). Vì vậy phát âm chỉ ra dạng nhận
    | xét chữ, không có điểm — và luôn kèm dòng nhắc cô nghe lại xác nhận.
    */
    'pronunciation_note' => 'Phát âm do AI nghe và nhận xét, chưa phải chấm chuyên sâu — cô nghe lại để xác nhận.',
];
