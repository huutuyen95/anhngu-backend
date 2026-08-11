# Trang "Lớp của em" (Học sinh) — FE STT5

Lộ trình buổi học của học sinh: danh sách buổi (chỉ buổi cô cho hiện) + nội dung từng buổi kèm
trạng thái riêng của em. Bảng "Bảng action" trong prompt gốc là đặc tả hành vi bắt buộc.

## Backend
- `GET /me/classrooms` — lớp em tham gia + `my_progress_pct`.
- `GET /classrooms/{id}/roadmap` — 403 nếu không thuộc lớp; trả `{classroom, stats, sessions[]}`.
  - Chỉ buổi `is_visible=true`. `locked` = buổi chưa tới `held_on` HOẶC chưa có nội dung.
  - Item chạy theo **Mission** của em (bỏ nháp + lịch chưa tới), status suy từ mission + attempt/
    view/card-progress: `todo|in_progress|submitted|pending_review|graded|viewed`.
  - Nội dung đã giao vẫn trả kể cả `is_published=false` (On/Off chỉ ảnh hưởng Thư viện).
  - `class_avg_score` là số tổng hợp (không kèm tên học sinh khác).
- Code: `App\Services\StudentRoadmapService`, `App\Http\Controllers\Api\StudentClassroomController`.

## Frontend
- `app/(app)/classes/page.tsx` + `features/classes/{content-card,roadmap-helpers}.tsx`.
- Lớp/buổi ghi vào URL `?class=&session=`; vào trang mở buổi active gần nhất chưa xong.
- CTA thẻ đổi theo loại + trạng thái (bảng 13 dòng), **tái dùng route hiện có** `/library/tests/[id]`
  (+`/attempt/`,`/result/`), `/library/documents/[id]`, `/library/vocab/[deckId]`.

## Giả định đã chọn
- Design `Learn English Student.dc.html` (màn wClass) KHÔNG có trong repo → dựng theo đặc tả hành vi
  + organic DS (`public/ds/organic.css`).
- CTA đi tới **route Thư viện có sẵn** thay vì tạo mới `/tests/[id]`, `/writing/[id]`, `/documents/[id]`
  (hành vi làm bài/tiếp tục/kết quả y hệt, tránh nhân đôi luồng attempt).
- Writing xem lại readonly dùng luôn trang kết quả `/library/tests/[id]/result/[attemptId]`.
- **Chưa làm**: confetti + toast "Xong buổi!" khi hoàn thành nhiệm vụ cuối buổi (E-2) và toast
  "Cô vừa gỡ nội dung" (E-3) — mới có **refetch khi quay lại tab** (E-1, qua `focus`). Confetti để
  lại vì cần thêm thư viện animation; refetch đã đảm bảo thẻ đổi trạng thái đúng.
