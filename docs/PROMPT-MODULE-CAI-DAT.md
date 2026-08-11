# Module Cài đặt hệ thống (Admin STT15)

Tài liệu chốt kiến trúc + danh sách cấu hình cho module Cài đặt. Nguồn sự thật của **danh sách
key** là `config/appsettings.php`; tài liệu này giải thích quyết định thiết kế.

## Quyết định đã chốt

| Vấn đề | Chốt |
|---|---|
| Lưu trữ | Bảng `settings(key, value, type, group, updated_by)` key–value, key phẳng theo chấm. |
| Cache | Toàn bộ settings cache 1 mảng (`Cache::rememberForever`), xoá cache khi lưu. Đọc qua `setting('key', default)`. |
| Kiểu | `type` ∈ string\|int\|float\|bool\|json\|file. Cast khi đọc, validate khi ghi. |
| Mặc định | Khai ở `config/appsettings.php`; DB chỉ lưu giá trị đã đổi khác default. Đặt lại đúng default → xoá bản ghi. |
| Áp dụng | Hiệu lực ngay. Bài đang làm dở giữ cấu hình lúc bắt đầu qua `test_attempts.config_snapshot`. |
| Mật khẩu SMTP | `Crypt::encryptString`; API trả `••••••••`; gửi rỗng/che = giữ nguyên. |
| Bật email | Chỉ bật `mail.enabled` sau khi gửi thử thành công (`mail.verified_at`). |
| Ảnh | `storage/app/public/branding/`; xoá file cũ khi thay (lúc lưu). |
| Lịch sử | `setting_changes` ghi mọi thay đổi, có Hoàn tác từng dòng. |
| Phân quyền | Chỉ `admin` vào Cài đặt (`role:admin`). |

## Endpoint (`/api/v1/admin/settings`, `role:admin`)

`GET /settings` · `PUT /settings {values}` · `POST /settings/reset {group|keys}` ·
`POST /settings/upload {key,file}` · `DELETE /settings/file {key}` ·
`GET /settings/changes` · `POST /settings/changes/{id}/revert` · `POST /settings/mail/test {to,config}`.
Công khai (không auth): `GET /public/branding`.

## Giả định đã chọn (deviations)

- **Prototype `Learn English Pages v2.dc.html` KHÔNG có màn A15** → UI dựng theo đặc tả chức năng
  (bố cục Magento-style: cột nhóm trái 250px + card field phải), không copy pixel.
- **`exam.leave_action` mặc định `warn`** (theo Bước 2). Đây là thay đổi so với hành vi cũ (luôn
  tự nộp khi vượt số lần) — giờ *cấu hình được*; admin đặt `autosubmit` để giữ hành vi tự nộp.
- **`grading.method` chưa đổi công thức chấm** (giữ `correct/gradable * total_score` để không phá
  130+ test cũ); snapshot vẫn lưu để về sau dùng. `grading.decimals`/`pass_score` phục vụ hiển thị.
- Option-field render dạng **radio pill** (≤3 lựa chọn) hoặc **Select** (nhiều hơn) thay vì
  radio-card có mô tả từng dòng (backend không cấp mô tả mỗi option).
- Đã wire `setting()` vào (dùng thật):
  - **exam**: `leave_limit`/`leave_action` + `config_snapshot` + `block_copy` + `autosubmit_on_timeout`
    (màn làm bài — FE tôn trọng theo snapshot lúc bắt đầu).
  - **grading**: `decimals` + `pass_score` (trang kết quả FE, theo snapshot).
  - **content**: `deck_complete_pct` (ngưỡng hoàn thành deck), `listening_max_plays`
    (mặc định số lần nghe cho section có audio).
  - **tts**: `default_voice` + `default_rate` (mặc định khi tạo deck).
  - **security**: `max_login_attempts` + `password_min` (đăng nhập/đăng ký).
  - **storage**: `max_file_mb` (upload tài liệu đính kèm).
  - **system**: `maintenance` (middleware). Thương hiệu qua `/public/branding` (favicon/title).
- CHƯA nối vì **tính năng chưa dựng** (không phải hardcode): `notify.*`, `mail.tpl_*`,
  `security.session_days`/`force_change_first_login`, `content.attempts_allowed`,
  `exam.max_extra_minutes`/`cheat_flag_threshold`/`allow_pause`/`disable_dictionary`,
  `content.writing_max/min_words` (đếm từ ở FE dùng `tests.word_limit` riêng). Các key này đã khai
  đủ + đọc được qua `setting()`, sẽ nối khi dựng tính năng tương ứng.
