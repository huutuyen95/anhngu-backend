<?php

/*
|--------------------------------------------------------------------------
| App Settings — nguồn sự thật của MỌI cấu hình hệ thống
|--------------------------------------------------------------------------
| DB (bảng settings) chỉ lưu giá trị đã bị đổi KHÁC default. Đọc luôn qua
| helper setting('exam.leave_limit', ...) hoặc App\Services\SettingService.
|
| Mỗi field: label, hint, type, default, (options?, unit?, required?, rules?,
| readonly?, secret?, accept?, max_kb?). `type` ∈ string|int|float|bool|json|file.
*/

return [
    'groups' => [

        // ── Thương hiệu & Hiển thị ─────────────────────────────────────────
        'brand' => [
            'label' => 'Thương hiệu & Hiển thị',
            'desc' => 'Logo, favicon, tên trung tâm và màu chủ đạo cho cả khu quản trị lẫn khu học sinh.',
            'icon' => 'palette',
            'fields' => [
                'brand.center_name' => ['label' => 'Tên trung tâm', 'hint' => 'Hiển thị trên đầu trang và email.', 'type' => 'string', 'default' => 'Anh ngữ Mrs Uyên', 'required' => true, 'rules' => ['string', 'max:120']],
                'brand.primary_color' => ['label' => 'Màu hệ thống', 'hint' => 'Màu chủ đạo của hệ thống (nút, nhấn mạnh) — mã hex, ví dụ #F2793B.', 'type' => 'string', 'default' => '#F2793B', 'rules' => ['string', 'regex:/^#([0-9a-fA-F]{6})$/']],
                'brand.admin.tab_title' => ['label' => 'Tiêu đề tab (Quản trị)', 'hint' => 'Chữ hiện trên tab trình duyệt khu quản trị.', 'type' => 'string', 'default' => 'Quản trị · Anh ngữ Mrs Uyên', 'rules' => ['string', 'max:120']],
                'brand.admin.logo' => ['label' => 'Logo khu quản trị', 'hint' => 'PNG/JPG/SVG, ≤ 2MB.', 'type' => 'file', 'default' => null, 'accept' => 'png,jpg,jpeg,svg', 'max_kb' => 2048],
                'brand.admin.favicon' => ['label' => 'Favicon khu quản trị', 'hint' => 'ICO/PNG/SVG, ≤ 1MB.', 'type' => 'file', 'default' => null, 'accept' => 'ico,png,svg', 'max_kb' => 1024],
                'brand.student.tab_title' => ['label' => 'Tiêu đề tab (Học sinh)', 'hint' => 'Chữ hiện trên tab trình duyệt khu học sinh.', 'type' => 'string', 'default' => 'Anh ngữ Mrs Uyên', 'rules' => ['string', 'max:120']],
                'brand.student.logo' => ['label' => 'Logo khu học sinh', 'hint' => 'PNG/JPG/SVG, ≤ 2MB.', 'type' => 'file', 'default' => null, 'accept' => 'png,jpg,jpeg,svg', 'max_kb' => 2048],
                'brand.student.favicon' => ['label' => 'Favicon khu học sinh', 'hint' => 'ICO/PNG/SVG, ≤ 1MB.', 'type' => 'file', 'default' => null, 'accept' => 'ico,png,svg', 'max_kb' => 1024],
                'brand.student.pwa_icon' => ['label' => 'Icon PWA (512×512)', 'hint' => 'PNG vuông 512×512, ≤ 1MB — dùng khi cài app.', 'type' => 'file', 'default' => null, 'accept' => 'png', 'max_kb' => 1024],
                'brand.student.banner' => ['label' => 'Banner đầu trang menu học sinh', 'hint' => 'Hiện ở đầu Nhiệm vụ, Lớp của em, Thư viện và Báo cáo. Nên dùng ảnh ngang 1920×420px, PNG/JPG, ≤ 4MB.', 'type' => 'file', 'default' => null, 'accept' => 'png,jpg,jpeg,webp', 'max_kb' => 4096],
                'brand.student.login_cover' => ['label' => 'Ảnh nền trang đăng nhập', 'hint' => 'PNG/JPG, ≤ 2MB.', 'type' => 'file', 'default' => null, 'accept' => 'png,jpg,jpeg', 'max_kb' => 2048],
            ],
        ],

        // ── Bài thi & Chống gian lận ──────────────────────────────────────
        'exam' => [
            'label' => 'Bài thi & Chống gian lận',
            'desc' => 'Số lần được rời màn hình, xử lý khi vi phạm, chặn sao chép và các tuỳ chọn khi làm bài.',
            'icon' => 'shield',
            'fields' => [
                'exam.leave_limit' => ['label' => 'Số lần được rời màn hình', 'hint' => 'Vượt quá sẽ xử lý theo hành động bên dưới.', 'type' => 'int', 'default' => 3, 'unit' => 'lần', 'rules' => ['integer', 'min:0', 'max:20']],
                'exam.leave_action' => ['label' => 'Xử lý khi vượt số lần', 'hint' => 'Ghi log ngầm, cảnh báo học sinh, hay tự động nộp bài.', 'type' => 'string', 'default' => 'warn', 'options' => ['log' => 'Chỉ ghi nhận', 'warn' => 'Cảnh báo học sinh', 'autosubmit' => 'Tự động nộp bài'], 'rules' => ['in:log,warn,autosubmit']],
                'exam.leave_notify_student' => ['label' => 'Báo cho học sinh khi rời màn hình', 'hint' => 'Hiện cảnh báo mỗi lần quay lại.', 'type' => 'bool', 'default' => true],
                'exam.block_copy' => ['label' => 'Chặn sao chép / dán', 'hint' => 'Vô hiệu chuột phải và Ctrl+C trong lúc làm bài.', 'type' => 'bool', 'default' => true],
                'exam.disable_dictionary' => ['label' => 'Tắt tra từ khi tự luyện', 'hint' => 'Bài cô giao trong lớp LUÔN cấm tra từ (tính như bài kiểm tra). Bật thêm cái này nếu muốn cấm cả ở Thư viện và Nhiệm vụ.', 'type' => 'bool', 'default' => false],
                'exam.cheat_flag_threshold' => ['label' => 'Ngưỡng gắn cờ nghi vấn', 'hint' => 'Số hành vi bất thường trước khi gắn cờ cho cô xem.', 'type' => 'int', 'default' => 5, 'unit' => 'lần', 'rules' => ['integer', 'min:1', 'max:50']],
                'exam.autosubmit_on_timeout' => ['label' => 'Tự nộp khi hết giờ', 'hint' => 'Hết thời gian thì hệ thống nộp bài giúp.', 'type' => 'bool', 'default' => true],
                'exam.max_extra_minutes' => ['label' => 'Thời gian gia hạn tối đa', 'hint' => 'Số phút tối đa cô có thể cộng thêm cho một lượt.', 'type' => 'int', 'default' => 15, 'unit' => 'phút', 'rules' => ['integer', 'min:0', 'max:120']],
                'exam.allow_pause' => ['label' => 'Cho phép tạm dừng', 'hint' => 'Học sinh có thể tạm dừng và làm tiếp sau.', 'type' => 'bool', 'default' => false],
            ],
        ],

        // ── Chấm điểm & Giới hạn ──────────────────────────────────────────
        'grading' => [
            'label' => 'Chấm điểm & Giới hạn',
            'desc' => 'Thang điểm, điểm đạt, số lần nghe, giới hạn số từ bài viết và ngưỡng hoàn thành.',
            'icon' => 'target',
            'fields' => [
                'grading.method' => ['label' => 'Cách tính điểm', 'hint' => 'Cách quy đổi số câu đúng ra điểm.', 'type' => 'string', 'default' => 'scale_10_even', 'options' => ['scale_10_even' => 'Thang 10, chia đều mỗi câu', 'scale_100_even' => 'Thang 100, chia đều mỗi câu', 'by_question_score' => 'Theo điểm từng câu'], 'rules' => ['in:scale_10_even,scale_100_even,by_question_score']],
                'grading.decimals' => ['label' => 'Số chữ số thập phân', 'hint' => 'Làm tròn điểm tới bao nhiêu chữ số.', 'type' => 'int', 'default' => 1, 'rules' => ['integer', 'min:0', 'max:2']],
                'grading.pass_score' => ['label' => 'Điểm đạt', 'hint' => 'Dưới mức này hiển thị màu đỏ.', 'type' => 'float', 'default' => 5.0, 'rules' => ['numeric', 'min:0', 'max:100']],
                'grading.show_explanation' => ['label' => 'Hiển thị lời giải', 'hint' => 'Khi nào học sinh xem được đáp án và lời giải.', 'type' => 'string', 'default' => 'after_submit', 'options' => ['after_submit' => 'Ngay sau khi nộp', 'after_due' => 'Sau hạn nộp', 'never' => 'Không bao giờ'], 'rules' => ['in:after_submit,after_due,never']],
                'content.writing_max_words' => ['label' => 'Số từ tối đa (bài viết)', 'hint' => 'Giới hạn trên cho câu viết.', 'type' => 'int', 'default' => 150, 'unit' => 'từ', 'rules' => ['integer', 'min:10', 'max:2000']],
                'content.writing_min_words' => ['label' => 'Số từ tối thiểu (bài viết)', 'hint' => 'Cảnh báo nếu viết ít hơn mức này.', 'type' => 'int', 'default' => 80, 'unit' => 'từ', 'rules' => ['integer', 'min:0', 'max:2000']],
                'content.listening_max_plays' => ['label' => 'Số lần nghe tối đa', 'hint' => 'Mặc định khi tạo phần nghe mới.', 'type' => 'int', 'default' => 2, 'unit' => 'lần', 'rules' => ['integer', 'min:1', 'max:10']],
                'content.attempts_allowed' => ['label' => 'Số lần làm lại', 'hint' => 'Số lượt được phép cho mỗi đề.', 'type' => 'int', 'default' => 1, 'unit' => 'lượt', 'rules' => ['integer', 'min:1', 'max:20']],
                'content.speaking_attempts_per_day' => ['label' => 'Lượt làm bài nói mỗi ngày', 'hint' => 'Chỉ áp cho đề nói ở Thư viện / Nhiệm vụ. Bài cô giao vẫn theo số lượt của nhiệm vụ.', 'type' => 'int', 'default' => 3, 'unit' => 'lượt/ngày', 'rules' => ['integer', 'min:1', 'max:50']],
                'content.deck_complete_pct' => ['label' => 'Ngưỡng hoàn thành bộ từ', 'hint' => 'Học thuộc bao nhiêu % thì tính là xong.', 'type' => 'int', 'default' => 80, 'unit' => '%', 'rules' => ['integer', 'min:10', 'max:100']],
            ],
        ],

        // ── Thông báo & Nhắc hạn ──────────────────────────────────────────
        'notify' => [
            'label' => 'Thông báo & Nhắc hạn',
            'desc' => 'Kênh thông báo, sự kiện gửi và thời điểm nhắc hạn cho học sinh và giáo viên.',
            'icon' => 'bell',
            'fields' => [
                'notify.web' => ['label' => 'Thông báo trên web', 'hint' => 'Hiện chuông thông báo trong ứng dụng.', 'type' => 'bool', 'default' => true],
                'notify.email' => ['label' => 'Thông báo qua email', 'hint' => 'Cần bật cấu hình email gửi đi.', 'type' => 'bool', 'default' => false],
                'notify.on_assign' => ['label' => 'Khi giao bài mới', 'hint' => 'Báo học sinh khi có bài/đề được giao.', 'type' => 'bool', 'default' => true],
                'notify.on_graded' => ['label' => 'Khi có điểm', 'hint' => 'Báo học sinh khi bài đã được chấm.', 'type' => 'bool', 'default' => true],
                'notify.on_session_open' => ['label' => 'Khi mở buổi học', 'hint' => 'Báo học sinh khi có buổi học mới.', 'type' => 'bool', 'default' => false],
                'notify.remind_before_hours' => ['label' => 'Nhắc trước hạn', 'hint' => 'Số giờ trước hạn nộp thì nhắc.', 'type' => 'int', 'default' => 24, 'unit' => 'giờ', 'rules' => ['integer', 'min:1', 'max:168']],
                'notify.daily_send_time' => ['label' => 'Giờ gửi nhắc hằng ngày', 'hint' => 'Định dạng HH:MM.', 'type' => 'string', 'default' => '19:00', 'rules' => ['regex:/^([01]\d|2[0-3]):[0-5]\d$/']],
                'notify.teacher_pending_grading' => ['label' => 'Nhắc cô có bài chờ chấm', 'hint' => 'Báo khi có bài viết/nói cần chấm tay.', 'type' => 'bool', 'default' => true],
                'notify.teacher_cheat_alert' => ['label' => 'Cảnh báo gian lận cho cô', 'hint' => 'Báo khi có lượt bị gắn cờ nghi vấn.', 'type' => 'bool', 'default' => true],
            ],
        ],

        // ── Chấm bài bằng AI ──────────────────────────────────────────────
        // Mặc định TẮT và chưa có khoá → toàn bộ hệ thống chạy y như khi chưa có tính năng
        // này: bài viết/nói vẫn vào hàng chờ cô chấm tay. Chỉ khi cô bật + dán khoá thì AI
        // mới bắt đầu đề xuất điểm.
        'ai' => [
            'label' => 'Chấm bài bằng AI',
            'desc' => 'TÍNH NĂNG SẼ PHÁT TRIỂN TRONG TƯƠNG LAI — hiện đang tạm khoá. Khi bật, AI sẽ tự đề xuất điểm và nhận xét cho bài viết / bài nói; cô vẫn là người duyệt cuối. Hiện tại cô dùng nút "Copy cho ChatGPT" ở màn chấm để tự chấm bằng tài khoản ChatGPT của mình.',
            'icon' => 'sparkles',
            'fields' => [
                'ai.enabled' => ['label' => 'Bật chấm bằng AI', 'hint' => 'Tắt thì mọi bài viết/nói vào hàng chờ cô chấm tay như cũ.', 'type' => 'bool', 'default' => false, 'readonly' => true],
                'ai.provider' => ['label' => 'Nhà cung cấp', 'hint' => 'Đổi nhà cung cấp không cần sửa code.', 'type' => 'string', 'default' => 'openai', 'options' => ['openai' => 'OpenAI (ChatGPT)'], 'rules' => ['in:openai'], 'readonly' => true],
                'ai.api_key' => ['label' => 'Khoá API', 'hint' => 'Lấy ở platform.openai.com → API keys. Được mã hoá, không bao giờ hiển thị lại.', 'type' => 'string', 'default' => '', 'secret' => true, 'rules' => ['nullable', 'string', 'max:200'], 'readonly' => true],
                'ai.text_model' => ['label' => 'Model chấm bài viết', 'hint' => 'Model rẻ đã đủ tốt cho bài viết ngắn.', 'type' => 'string', 'default' => 'gpt-5.4-mini', 'rules' => ['string', 'max:60'], 'readonly' => true],
                'ai.audio_model' => ['label' => 'Model chấm bài nói', 'hint' => 'Phải là model nghe được audio.', 'type' => 'string', 'default' => 'gpt-audio', 'rules' => ['string', 'max:60'], 'readonly' => true],
                'ai.monthly_budget_usd' => ['label' => 'Hạn mức mỗi tháng', 'hint' => 'Dùng hết thì ngừng chấm AI và báo cô; bài vẫn vào hàng chờ chấm tay.', 'type' => 'float', 'default' => 15.0, 'unit' => 'USD', 'rules' => ['numeric', 'min:0', 'max:1000'], 'readonly' => true],
                'ai.grade_writing' => ['label' => 'Chấm bài viết', 'hint' => 'Cho AI đề xuất điểm câu viết.', 'type' => 'bool', 'default' => true, 'readonly' => true],
                'ai.grade_speaking' => ['label' => 'Chấm bài nói', 'hint' => 'Cho AI nghe và đề xuất điểm câu nói. Tốn hơn bài viết khoảng 10 lần.', 'type' => 'bool', 'default' => true, 'readonly' => true],
            ],
        ],

        // ── Email gửi đi (SMTP) ───────────────────────────────────────────
        'mail' => [
            'label' => 'Email gửi đi (SMTP)',
            'desc' => 'Máy chủ gửi thư và các mẫu email. Phải gửi thử thành công mới bật được.',
            'icon' => 'mail',
            'fields' => [
                'mail.enabled' => ['label' => 'Bật gửi email', 'hint' => 'Chỉ bật được sau khi gửi thử thành công.', 'type' => 'bool', 'default' => false],
                'mail.provider' => ['label' => 'Nhà cung cấp', 'hint' => 'Chọn Gmail để tự điền máy chủ.', 'type' => 'string', 'default' => 'custom', 'options' => ['gmail' => 'Gmail', 'service' => 'Dịch vụ email', 'custom' => 'Tuỳ chỉnh'], 'rules' => ['in:gmail,service,custom']],
                'mail.host' => ['label' => 'Máy chủ SMTP', 'hint' => 'Ví dụ smtp.gmail.com.', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:190']],
                'mail.port' => ['label' => 'Cổng', 'hint' => 'TLS thường là 587, SSL là 465.', 'type' => 'int', 'default' => 587, 'rules' => ['integer', 'min:1', 'max:65535']],
                'mail.encryption' => ['label' => 'Mã hoá', 'hint' => 'TLS/SSL/không.', 'type' => 'string', 'default' => 'tls', 'options' => ['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'Không'], 'rules' => ['in:tls,ssl,none']],
                'mail.username' => ['label' => 'Tên đăng nhập', 'hint' => 'Thường là địa chỉ email.', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:190']],
                'mail.password' => ['label' => 'Mật khẩu / App password', 'hint' => 'Được mã hoá, không bao giờ hiển thị lại.', 'type' => 'string', 'default' => '', 'secret' => true, 'rules' => ['nullable', 'string', 'max:190']],
                'mail.verified_at' => ['label' => 'Đã xác minh lúc', 'hint' => 'Thời điểm gửi thử thành công gần nhất.', 'type' => 'string', 'default' => null, 'readonly' => true],
                'mail.from_name' => ['label' => 'Tên người gửi', 'hint' => 'Hiện ở mục "Từ" trong thư.', 'type' => 'string', 'default' => 'Anh ngữ Mrs Uyên', 'rules' => ['nullable', 'string', 'max:120']],
                'mail.from_address' => ['label' => 'Địa chỉ gửi', 'hint' => 'Email đứng tên gửi.', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'email', 'max:190']],
                'mail.reply_to' => ['label' => 'Địa chỉ trả lời', 'hint' => 'Email nhận thư khi học sinh bấm Trả lời.', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'email', 'max:190']],
                'mail.hourly_limit' => ['label' => 'Giới hạn thư/giờ', 'hint' => 'Tránh bị nhà cung cấp chặn.', 'type' => 'int', 'default' => 80, 'unit' => 'thư', 'rules' => ['integer', 'min:1', 'max:10000']],
                'mail.tpl_reset_password' => ['label' => 'Mẫu: Đặt lại mật khẩu', 'hint' => 'Gửi khi học sinh quên mật khẩu.', 'type' => 'bool', 'default' => true],
                'mail.tpl_new_assignment' => ['label' => 'Mẫu: Giao bài mới', 'hint' => 'Gửi khi có bài/đề mới.', 'type' => 'bool', 'default' => true],
                'mail.tpl_graded' => ['label' => 'Mẫu: Đã chấm điểm', 'hint' => 'Gửi khi bài được chấm.', 'type' => 'bool', 'default' => true],
                'mail.tpl_due_reminder' => ['label' => 'Mẫu: Nhắc hạn nộp', 'hint' => 'Gửi trước hạn nộp.', 'type' => 'bool', 'default' => false],
                'mail.signature' => ['label' => 'Chữ ký cuối thư', 'hint' => 'Dòng ký tên hiện cuối mỗi email.', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:500']],
            ],
        ],

        // ── Tài khoản & Hệ thống ──────────────────────────────────────────
        'system' => [
            'label' => 'Tài khoản & Hệ thống',
            'desc' => 'Bảo mật đăng nhập, thùng rác, dung lượng tệp, giọng đọc mặc định và chế độ bảo trì.',
            'icon' => 'settings',
            'fields' => [
                'security.password_min' => ['label' => 'Độ dài mật khẩu tối thiểu', 'hint' => 'Số ký tự tối thiểu khi đặt mật khẩu.', 'type' => 'int', 'default' => 8, 'unit' => 'ký tự', 'rules' => ['integer', 'min:6', 'max:64']],
                'security.session_days' => ['label' => 'Thời gian giữ đăng nhập', 'hint' => 'Bao nhiêu ngày thì phải đăng nhập lại.', 'type' => 'int', 'default' => 7, 'unit' => 'ngày', 'rules' => ['integer', 'min:1', 'max:365']],
                'security.max_login_attempts' => ['label' => 'Số lần đăng nhập sai', 'hint' => 'Vượt quá sẽ khoá tạm thời.', 'type' => 'int', 'default' => 5, 'unit' => 'lần', 'rules' => ['integer', 'min:3', 'max:20']],
                'security.force_change_first_login' => ['label' => 'Bắt đổi mật khẩu lần đầu', 'hint' => 'Học sinh phải đổi mật khẩu ở lần đăng nhập đầu.', 'type' => 'bool', 'default' => true],
                'security.trash_retention_days' => ['label' => 'Giữ thùng rác', 'hint' => 'Số ngày trước khi xoá vĩnh viễn.', 'type' => 'int', 'default' => 30, 'unit' => 'ngày', 'rules' => ['integer', 'min:1', 'max:365']],
                'storage.max_file_mb' => ['label' => 'Dung lượng tệp tối đa', 'hint' => 'Giới hạn cho mỗi lần tải lên.', 'type' => 'int', 'default' => 50, 'unit' => 'MB', 'rules' => ['integer', 'min:1', 'max:500']],
                'tts.default_voice' => ['label' => 'Giọng đọc mặc định', 'hint' => 'Dùng khi thẻ từ chưa chọn giọng riêng.', 'type' => 'string', 'default' => 'en-GB-female', 'options' => ['en-GB-female' => 'Anh - Nữ', 'en-GB-male' => 'Anh - Nam', 'en-US-female' => 'Mỹ - Nữ', 'en-US-male' => 'Mỹ - Nam'], 'rules' => ['in:en-GB-female,en-GB-male,en-US-female,en-US-male']],
                'tts.default_rate' => ['label' => 'Tốc độ đọc mặc định', 'hint' => '1.0 là bình thường, nhỏ hơn là chậm.', 'type' => 'float', 'default' => 0.9, 'rules' => ['numeric', 'min:0.5', 'max:1.5']],
                'system.maintenance' => ['label' => 'Chế độ bảo trì', 'hint' => 'Tạm khoá khu học sinh; khu quản trị vẫn vào được.', 'type' => 'bool', 'default' => false],
                'system.maintenance_message' => ['label' => 'Thông báo bảo trì', 'hint' => 'Nội dung hiển thị cho học sinh khi bảo trì.', 'type' => 'string', 'default' => 'Hệ thống đang bảo trì, em quay lại sau ít phút nhé!', 'rules' => ['nullable', 'string', 'max:300']],
            ],
        ],

    ],
];
