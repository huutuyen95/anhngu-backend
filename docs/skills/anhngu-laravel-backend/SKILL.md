---
name: anhngu-laravel-backend
description: >-
  Laravel best practices for anhngu-backend: Architecture (Repository, Service,
  Form Request), controllers, models, migrations, policies, jobs, Eloquent,
  Sanctum APIs, N+1, caching, validation, security, routing, tests.
---

# Anh ngữ — Laravel Backend

Nguồn: Laravel Best Practices ([skillsdirectory / laravel boost](https://www.skillsdirectory.com/skills/karuhun-developer-laravel-best-practices)).  
Chạy lệnh **qua Docker** từ `anhngu-infra` (`docker compose exec backend ...`).

## Consistency first

Trước khi áp rule “lý tưởng”, xem sibling files trong repo. Pattern đang dùng thắng pattern mới nếu cả hai đều hợp lệ.

## Architecture patterns

Chuẩn mục tiêu cho API mới / refactor: **Form Request → Controller → Service → Repository → Eloquent**.

```
HTTP Request
    ↓
Form Request          (validate + authorize nhẹ)
    ↓
Controller            (mỏng: gọi Service, trả JSON / Resource)
    ↓
Service               (business: chấm điểm, giao mission, bắt đầu attempt…)
    ↓
Repository            (query / persist Eloquent — không nhét rule nghiệp vụ)
    ↓
Model / DB
```

### Form Request (Validate)

- Mọi input từ client qua **Form Request** — không `$request->validate()` trong controller (trừ prototype 1-off rồi chuyển ngay).
- Dùng `$request->validated()` (hoặc `safe()->only(...)`) — **không** `$request->all()`.
- Rule đặt trong `rules()`; message VI trong `messages()` khi UX cần.
- `authorize()` cho quyền đơn giản; quyền phức tạp → Policy + `$this->authorize()` trong controller/service.
- Đặt tại `app/Http/Requests/{Domain}/` — vd `StoreAttemptAnswerRequest`, `AssignMissionRequest`.

### Controller

- Mỏng: inject Service (hoặc resolve), gọi 1 method nghiệp vụ, return response.
- Không viết query Eloquent dài, không chấm điểm / side-effect trong controller.
- Dùng route model binding; `apiResource` khi CRUD chuẩn.
- Response: `response()->json(...)` hoặc **API Resource** khi cần ẩn field (đáp án) / shape ổn định.

### Service

- Đặt tại `app/Services/` — vd `AttemptGradingService`, `MissionService`, `DeckProgressService`.
- Chứa **business rules**: chấm MCQ/fill/select, tính `total_score`, cập nhật mission done, ghi `activity_logs`, transaction.
- Một service có thể gọi nhiều repository; **không** phụ thuộc HTTP (`Request` / session) — nhận DTO/array/`validated()` + User.
- Side-effect dài / retry → Job gọi từ Service, không từ Controller trực tiếp nếu logic đã ở Service.

### Repository

- Đặt tại `app/Repositories/` — vd `DeckRepository`, `TestAttemptRepository`.
- Chỉ **data access**: `find`, `paginateForStudent`, `updateOrCreateProgress`, eager-load chuẩn (`with`, `withCount`).
- Không validate HTTP; không gửi mail/event trừ khi team thống nhất (ưu tiên Service fire event).
- Eloquent Model vẫn giữ quan hệ, cast, scope nhỏ (`scopePublished`) — Repository compose các query đó.
- **Không** bắt buộc interface + binding cho mọi repo ở MVP; thêm interface khi cần fake/test hoặc đổi nguồn dữ liệu.

### Model

- Quan hệ có return type; `$fillable` / `$hidden` (đáp án); `casts()` / Enum.
- Scope tái sử dụng được (`published()`, `forClassroom()`).
- Không nhét orchestration đa model vào Model (để Service).

### API Resource (khi cần)

- `app/Http/Resources/` — ẩn `is_correct` / `explanation` với HV trước khi nộp.
- Student vs result: Resource khác hoặc `when($request->user()..., ...)`.

### Ví dụ luồng (nộp bài)

1. `SubmitAttemptRequest` validate payload câu trả lời.
2. `TestAttemptController@submit` → `AttemptService::submit($attempt, $validated, $user)`.
3. Service: transaction → Repository lưu answers → chấm → cập nhật scores → mission/activity.
4. Trả `AttemptResultResource`.

### Không làm (tránh over-engineering MVP)

- Repository rỗng chỉ wrap `Model::all()`.
- God Service 2000 dòng — tách theo domain (Attempt / Mission / Deck…).
- CQRS / Action class chồng Service trừ khi 1 use-case thật sự độc lập và team đã dùng.
- Validate vừa Request vừa thủ công lặp lại trong Service (Service tin data đã validated; chỉ assert invariant nghiệp vụ).

## Ưu tiên cao (MVP này)

### Database / Eloquent

- Eager load `with()`; tránh N+1. Dev: cân nhắc `Model::preventLazyLoading()`.
- `withCount()` thay vì load relation chỉ để đếm.
- Index cột `WHERE` / `ORDER BY` / `JOIN`.
- Cast trong `casts()`; relationship có return type.
- `$fillable` / `$guarded` trên mọi model.

### Security (API Sanctum)

- Authorize theo `role` middleware đã có; không tin input client.
- Không raw SQL với user input.
- Validate MIME/size khi upload (câu `upload`).
- Ẩn đáp án đúng (`is_correct` hidden) khi trả đề cho học sinh.
- Không commit `.env`; đọc config qua `config()` / env Docker.

### Validation & Controllers

- Xem **Architecture patterns** (Form Request → Controller → Service → Repository).
- `apiResource` / route model binding khi phù hợp; throttle auth routes.

### Migrations

- `constrained()` cho FK; một concern/migration.
- Không sửa migration đã chạy production — tạo migration mới.
- Mirror default cột trong model `$attributes` nếu cần.

### Testing

- Feature test auth, deck, attempt qua `docker compose exec backend php artisan test`.
- Factory states; fakes sau khi setup factory.

### Caching / Queue (khi dùng)

- `Cache::remember()`; lock khi race condition.
- Job: `timeout` < `retry_after`; implement `failed()`; unique khi cần.

## Mapping nhanh theo file

| Đang sửa | Đọc rule |
|----------|----------|
| Migration | Migrations, DB performance |
| Model / query | Eloquent, N+1, Repository |
| Form Request | Architecture — Validate |
| Service / chấm điểm | Architecture — Service |
| Controller / API | Architecture, routing, security |
| Job / schedule | Queue, scheduling |
| Test | Testing |

## Không làm trong skill này

- UI Next.js → dùng `ui-ux-pro-max`
- Clone hết admin UUP → chỉ làm theo sprint MVP (`KE-HOACH-SPRINT.md`)
