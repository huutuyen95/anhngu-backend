# CLAUDE.md — anhngu-backend

API backend cho website học tiếng Anh (dùng nội bộ, giáo viên + học sinh).
Frontend là project Next.js riêng (`anhngu-frontend`), gọi API này.

## Stack

- **Laravel 12**, **PHP 8.4**, **MySQL 8**, **Redis**.
- Auth: **Laravel Sanctum** (token-based).
- Chạy trong **Docker** (php-fpm + nginx). `docker-compose.yml` nằm ở repo `anhngu-infra`
  (thư mục cha, repo này là submodule `backend/` của nó).

## ⚠️ Chạy lệnh — BẮT BUỘC qua Docker

Máy dev KHÔNG cài PHP/Composer/MySQL trực tiếp. MỌI lệnh phải chạy trong container,
gọi từ thư mục `anhngu-infra`:

```bash
docker compose exec backend php artisan <lệnh>
docker compose exec backend composer <lệnh>
docker compose exec backend php artisan test
```

KHÔNG gọi `php`, `composer`, `artisan` trực tiếp trên máy (không có, sẽ lỗi).
Sửa file code trong `backend/` là container thấy ngay (bind-mount).

## Kiến trúc

- **API-only**, prefix `/api/v1` (`routes/api.php`).
- Auth bằng Sanctum token. Phân quyền theo `role` (admin / teacher / student) qua middleware
  `role:teacher,admin` (alias khai trong `bootstrap/app.php`).
- Cấu hình đọc từ **biến môi trường** (docker `env_file` ở infra: `env/backend.env`).
  KHÔNG sửa `.env` thủ công, KHÔNG commit `.env`.

## Domain chính

- **Lớp học**: `classrooms` → `class_sessions` (buổi) → `session_items` (polymorphic).
- **Đề thi**: `tests` → `test_parts` → `test_sections` → `questions` → `question_options`.
  Làm bài: `test_attempts` → `attempt_answers` (+ `attempt_skill_scores` cho đề combo).
- **Flashcard**: `decks` → `cards` → `card_progress`.
- **Nhiệm vụ**: `missions` (polymorphic). **Log hoạt động**: `activity_logs` (dựng báo cáo).

### 4 loại câu hỏi (đúng theo hệ thống gốc — KHÔNG tự thêm dạng khác)

`questions.type` ∈ `multiple_choice` | `fill_blank` | `select` | `upload`.
Chi tiết + cách chấm điểm xem `docs/PHAN-TICH-DE-THI.md`.

## Quy ước

- **Bảo mật đáp án**: `QuestionOption` để `is_correct` trong `$hidden` — không lộ đáp án đúng
  khi trả đề cho học sinh. Chỉ endpoint kết quả (sau khi nộp) mới trả đáp án đúng.
- **Timer server-side**: deadline = `started_at + duration`. Không tin timer client.
- **Chấm điểm**: chỉ tự chấm `multiple_choice`/`fill_blank`/`select`. `upload` (nói/viết) để
  `is_correct = null`, chờ AI hoặc giáo viên chấm (giai đoạn sau).
- Git: Conventional Commits (`feat:`/`fix:`/...), làm nhánh `feature/...` → PR → `main`.
- KHÔNG commit `vendor/`, `.env`.

## Tài liệu tham khảo (thư mục `docs/`)

- `docs/PHAN-TICH-DE-THI.md` — thiết kế engine đề thi (4 loại câu, schema, cốt lõi vs để sau).
- `docs/KE-HOACH-SPRINT.md` — roadmap 4 sprint / 1 tháng.
- Data model & phân tích chức năng tổng thể.

## Kiểm tra sau khi sửa

```bash
docker compose exec backend php artisan test
docker compose exec backend php artisan route:list   # xem route hiện có
```