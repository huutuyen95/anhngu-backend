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
docker compose -f ../docker-compose.yml exec backend php artisan <lệnh>
docker compose -f ../docker-compose.yml exec backend composer <lệnh>
docker compose -f ../docker-compose.yml exec backend php artisan test
```

(Gọi từ thư mục `anhngu-infra` thì bỏ `-f ../docker-compose.yml` đi cũng được.)

KHÔNG gọi `php`, `composer`, `artisan` trực tiếp trên máy (không có, sẽ lỗi).
Sửa file code trong `backend/` là container thấy ngay (bind-mount).

## Kiến trúc

- **API-only**, prefix `/api/v1` (`routes/api.php`).
- Auth bằng Sanctum token. **2 loại token tách biệt theo khu** (cấp lúc login/register qua
  `User::issueRoleToken()`): token **`student`** (abilities `['student']`) và token **`teacher`**
  (teacher `['teacher','student']`, admin `['admin','teacher','student']`). Token học sinh KHÔNG
  có ability `teacher` nên không gọi được endpoint khu giáo viên.
- Phân quyền 2 lớp: middleware **`role:teacher,admin`** (chốt theo `role` trong DB — nguồn chuẩn,
  chạy cả trong test) + **`token:teacher`** (`EnsureTokenScope` — chặn theo phạm vi token thật;
  tự bỏ qua khi auth không qua PAT thật, vd `actingAs` trong test). Cả hai alias khai ở
  `bootstrap/app.php`.
- Cấu hình đọc từ **biến môi trường** (docker `env_file` ở infra: `env/backend.env`).
  KHÔNG sửa `.env` thủ công, KHÔNG commit `.env`.

## Domain chính

- **Lớp học**: `classrooms` → `class_sessions` (buổi) → `session_items` (polymorphic).
- **Đề thi**: `tests` → `test_parts` → `test_sections` → `questions` → `question_options`.
  Làm bài: `test_attempts` → `attempt_answers` (+ `attempt_skill_scores` cho đề combo).
  Đề xếp theo thư mục: `test_categories` (`tests.category_id`).
- **Flashcard**: `decks` → `cards` → `card_progress`.
- **Nhiệm vụ**: `missions` (polymorphic). **Log hoạt động**: `activity_logs` (dựng báo cáo).
- **Cài đặt hệ thống**: `settings(key,value,type,group)` key–value (chỉ lưu giá trị khác default) +
  `setting_changes` (lịch sử + hoàn tác). Nguồn sự thật danh sách key: `config/appsettings.php`.
  Đọc mọi nơi qua helper `setting('exam.leave_limit', 3)` (cache 1 mảng, xoá khi lưu) hoặc
  `App\Services\SettingService`. Chi tiết: `docs/PROMPT-MODULE-CAI-DAT.md`. Chỉ **super admin**
  (`users.is_super_admin = true`) vào được (`/api/v1/admin/settings`, middleware `superadmin`) —
  admin thường cũng bị chặn. Thương hiệu công khai ở `GET /public/branding`.
  Middleware `maintenance` (`system.maintenance`) chặn khu học sinh, admin/teacher vẫn vào.

### Loại câu hỏi (chốt theo `App\Enums\QuestionType` — KHÔNG tự thêm dạng khác)

`questions.type` ∈ `multiple_choice` | `fill_blank` | `select` | `upload` | `writing` | `speaking`.

- Cột `questions.type` nay là `string(30)` (migration `2026_07_31_090001`) chứ không còn `enum` DB —
  thêm case mới chỉ cần sửa `QuestionType`, không phải ALTER MODIFY.
- `upload` là case **cũ còn sót lại** trong enum: `TestGradingService` vẫn không tự chấm nó, nhưng
  câu mới dùng `writing` (viết) / `speaking` (nói). Đừng tạo câu `upload` mới.

Chi tiết + cách chấm điểm xem `docs/PHAN-TICH-DE-THI.md`.

### Trường phục vụ writing / speaking (verify qua migrations)

- `questions.images` (json, cast `array`) — danh sách URL ảnh gợi ý (câu speaking mô tả tranh...).
- `questions.record_limit_seconds` — giới hạn thời lượng ghi âm (giây); `null` = không giới hạn.
- `attempt_answers.answer_file_url` — URL audio học sinh nộp cho câu speaking.
- `attempt_answers.play_count` + `test_sections.max_plays` — đếm & chặn số lần nghe ở server.
- `attempt_answers.score` / `feedback` / `graded_by` / `graded_at` — kết quả cô chấm tay.
- `tests.word_limit`, `tests.rubric` — cấu hình câu writing. `tests.shuffle_questions`,
  `tests.ai_grading` (mặc định `false`, AI chấm để giai đoạn sau).

### Endpoint đề thi (prefix `/api/v1`, xem `routes/api.php`)

Học sinh (`auth:sanctum`):

- `GET tests` — danh sách đề đã publish + lượt điểm cao nhất của mình.
- `GET tests/{test}` — cấu trúc đề (404 nếu chưa publish), ẩn đáp án.
- `POST tests/{test}/attempts` · `GET attempts/{attempt}` (trạng thái lượt: hạn nộp tính
  server-side `started_at + duration`, đáp án đã lưu để làm tiếp, `tab_exit_count/limit`) ·
  `PUT attempts/{attempt}/answers` · `POST attempts/{attempt}/submit` · `GET attempts/{attempt}/result`.
- **Chống rời tab** (`POST attempts/{attempt}/tab-exit`): đếm số lần học sinh rời màn thi
  (lưu ở `test_attempts.tab_exit_count` để reload không reset). Vượt `TestAttempt::TAB_EXIT_LIMIT`
  (mặc định 3) → server **tự nộp bài** và trả kết quả. Deadline KHÔNG truyền qua URL nữa.
- `POST attempts/{attempt}/answers/{question}/audio` — nộp audio câu speaking
  (mp3/m4a/wav/ogg/aac/webm, ≤ 20 MB) · `DELETE` cùng đường dẫn để xoá ghi lại
  (`AttemptAudioService`).

Giáo viên / admin (`role:teacher,admin`):

- `POST media/upload` — upload ảnh (jpg/jpeg/png/webp ≤ 2 MB) hoặc audio khi `type=audio`
  (mp3/m4a/wav/ogg/aac ≤ 20 MB); trả `{ url }`.
- `admin/tests` (apiResource) + `admin/tests/{test}/duplicate|preflight|category|structure`,
  import Word (`admin/tests/import-word`, `.../commit`, `admin/tests/word-template`),
  `admin/tests/{test}/sections/{section}/audio`, `admin/test-categories`.
- `GET admin/attempts` (mặc định lọc `status=pending_review`) · `GET admin/attempts/{attempt}` ·
  `POST admin/attempts/{attempt}/grade`.

## Quy ước

- **Bảo mật đáp án**: `QuestionOption` để `is_correct` trong `$hidden`, và `TestDetailResource`
  chỉ đưa `is_correct` + `explanation` vào output khi `revealAnswers: true`. Học sinh làm bài
  (`GET /tests/{test}`) → `revealAnswers: false`; endpoint kết quả sau khi nộp và khu admin sửa đề
  → `true`. Cờ thứ hai `forTeacher: true` (chỉ `AdminTestController`) lộ thêm cấu hình đề
  (`is_published`, `rubric`, `scoring_method`, `shuffle_questions`, `category_id`...).
- **Timer server-side**: deadline = `started_at + duration`. Không tin timer client.
- **Chấm điểm** (`TestGradingService` → `AttemptGradingService`):
  - Nộp bài: tự chấm `multiple_choice`/`select`/`fill_blank`; `writing`/`speaking`/`upload` để
    `is_correct = null`. Điểm = `correct / gradable * tests.total_score`.
  - Đề **có câu `writing`/`speaking`** → attempt sang `pending_review`, giữ điểm tạm (chỉ phần tự
    chấm) và **chưa** dedup lượt điểm cao nhất.
  - Giáo viên chấm tay qua `POST /admin/attempts/{attempt}/grade` (điểm + nhận xét từng câu, clamp
    theo `questions.score`) → ghi `graded_by`/`graded_at`, tính lại tổng, chạy
    `reconcileBestAttempt(..., 'graded')` → attempt sang `graded`.
  - Đề không có câu nói/viết → nộp xong là `submitted` luôn.
  - AI chấm chưa làm — mới có cờ `tests.ai_grading`.
- **Trạng thái attempt** (`test_attempts.status`, string): `in_progress` → `pending_review` →
  `graded`, hoặc `in_progress` → `submitted`.
- **Dedup lượt làm**: mỗi (user, test) chỉ giữ 1 dòng `test_attempts` = lượt điểm cao nhất
  (`reconcileBestAttempt` xoá lượt thấp hơn kèm `attempt_answers`).
- Git: Conventional Commits (`feat:`/`fix:`/...), làm nhánh `feature/...` → PR → `main`.
- KHÔNG commit `vendor/`, `.env`.

## Tài liệu tham khảo (thư mục `docs/`)

- `docs/PHAN-TICH-DE-THI.md` — thiết kế engine đề thi (4 loại câu, schema, cốt lõi vs để sau).
- `docs/KE-HOACH-SPRINT.md` — roadmap 4 sprint / 1 tháng.
- Data model & phân tích chức năng tổng thể.

## Kiểm tra sau khi sửa

```bash
docker compose -f ../docker-compose.yml exec backend php artisan test
docker compose -f ../docker-compose.yml exec backend php artisan route:list   # xem route hiện có
```