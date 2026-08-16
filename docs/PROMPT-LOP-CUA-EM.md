# Trang "Lớp của em" (Học sinh) — FE STT5

Lộ trình buổi học của học sinh: danh sách buổi (chỉ buổi cô cho hiện) + nội dung từng buổi kèm
trạng thái riêng của em. Bảng "Bảng action" trong prompt gốc là đặc tả hành vi bắt buộc.

## Backend
- `GET /me/classrooms` — dữ liệu **card chọn lớp**: `{id, name, code, teacher_name, students_count,
  schedule_text, starts_on, ends_on, status, progress_pct, done_count, total_count, todo_count,
  due_soon_count (≤3 ngày), avg_score (thang 10, 1 số lẻ, bỏ lượt chờ chấm), last_activity_at}`.
  - `code` suy từ tên (token có số, vd "12F"/"11A1"); `schedule_text=null` (chưa có cột lịch).
  - Sắp xếp: **active** trước (due_soon nhiều lên đầu, rồi ends_on gần nhất) → **upcoming** → **ended**.
- `GET /classrooms/{id}/roadmap?session={id?}` — auth qua **`ClassroomPolicy::viewRoadmap`**
  (403 "Em không ở trong lớp này." cho người ngoài lớp, kể cả giáo viên); trả `{classroom(+code), stats, sessions[]}`.
  - Chỉ buổi `is_visible=true`. `locked` = buổi chưa tới `held_on` HOẶC chưa có nội dung.
  - **Buổi locked KHÔNG trả `items`** (không lộ nội dung buổi chưa mở).
  - Item chạy theo **Mission** của em (bỏ nháp + lịch chưa tới), status suy từ mission + attempt/
    view/card-progress: `todo|in_progress|submitted|pending_review|graded|viewed`.
  - Nội dung đã giao vẫn trả kể cả `is_published=false` (On/Off chỉ ảnh hưởng Thư viện).
  - `class_avg_score` là số tổng hợp (không kèm tên học sinh khác).
- Code: `App\Services\StudentRoadmapService`, `App\Http\Controllers\Api\StudentClassroomController`.

## Frontend — 2 màn
- **Chọn lớp** `app/(app)/classes/page.tsx` (wClassPick): lưới card. **0 lớp** → rỗng
  "Em chưa được thêm vào lớp nào — nhắn cô Uyên nhé"; **1 lớp** → `router.replace('/classes/{id}')`
  (không hiện picker); **≥2 lớp** → lưới card. Card lớp `active` = **nền accent, nút "Vào lớp" trắng**
  (`.btn-secondary` + `background:var(--color-bg)`); lớp khác nền neutral, nút `.btn-primary`.
- **Chi tiết lớp** `app/(app)/classes/[classId]/page.tsx` (wClass) + `features/classes/{content-card,roadmap-helpers}.tsx`.
  - Breadcrumb "‹ TẤT CẢ LỚP CỦA EM · N LỚP" (chỉ khi ≥2 lớp) → `/classes`.
  - Cụm đổi lớp nhanh: **≤3 lớp = pill**, **≥4 lớp = dropdown tự dựng** (KHÔNG dùng `<select>`).
  - Buổi ghi vào URL `?session=`; vào trang mở buổi active gần nhất chưa xong.
- Nav "Lớp của em" active trên **cả** `/classes` và `/classes/[classId]` (matchActive dùng `startsWith`).
- CTA thẻ đổi theo loại + trạng thái (bảng 13 dòng). Đề/writing đi qua **root của lớp** kèm
  `?mission=` (`classTestsRoot`) để lượt gắn đúng nhiệm vụ; document/deck dùng route Thư viện.

### Điểm mới (bản cập nhật, 1 phase)
1. **KHÔNG dùng `<select>/<option>` ở đâu cả.** Đổi lớp = **cụm pill**; chọn buổi trên mobile =
   **dải pill cuộn ngang** (desktop vẫn là rail dọc).
2. **1 lớp → vào thẳng**, không hiện picker; **nhiều lớp → cụm pill** (pill đang chọn nền accent
   chữ `--color-bg`).
3. **Lớp đã kết thúc vẫn xem lại được**: `myClassrooms` trả cả lớp `ended` → pill có nhãn
   "· đã kết thúc", banner "Lớp đã kết thúc…", mọi CTA thẻ đổi thành **"Xem lại"**.
4. **Quy tắc nút trên nền accent**: nút đặt trên nền accent phải dùng **màu trắng/`--color-bg`**,
   KHÔNG đặt nút accent trên nền accent (đọc ngược trạng thái). Trang này giữ nền trang cream,
   card trên `neutral`, nên `.btn-primary` (accent) chỉ nằm trên nền sáng — không vi phạm.

## Giả định đã chọn
- `classrooms` KHÔNG có cột `code`/`schedule` → `code` suy từ tên, `schedule_text=null`.
- Đếm nhiệm vụ theo `mission.status` (các luồng nộp bài/học deck/xem tài liệu đã tự set `done`).
- "Nền accent" chỉ áp cho card lớp **đang học** ở màn chọn lớp (nút trắng ở đó); các nền còn lại
  là cream/neutral nên `.btn-primary` không vi phạm quy tắc nút.
- Design `Learn English Student.dc.html` (màn wClass) KHÔNG có trong repo → dựng theo đặc tả hành vi
  + organic DS (`public/ds/organic.css`).
- CTA đi tới **route Thư viện có sẵn** thay vì tạo mới `/tests/[id]`, `/writing/[id]`, `/documents/[id]`
  (hành vi làm bài/tiếp tục/kết quả y hệt, tránh nhân đôi luồng attempt).
- Writing xem lại readonly dùng luôn trang kết quả `/library/tests/[id]/result/[attemptId]`.
- **Chưa làm**: confetti + toast "Xong buổi!" khi hoàn thành nhiệm vụ cuối buổi (E-2) và toast
  "Cô vừa gỡ nội dung" (E-3) — mới có **refetch khi quay lại tab** (E-1, qua `focus`). Confetti để
  lại vì cần thêm thư viện animation; refetch đã đảm bảo thẻ đổi trạng thái đúng.
