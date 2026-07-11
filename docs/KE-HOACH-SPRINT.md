# Kế hoạch Sprint — Dự án Anh ngữ (MVP trong 1 tháng)

## Giả định

- **2 người**, mỗi người **~3h/ngày** (~15–18h/tuần) → nhóm **~30h+/tuần**.
  *(Giả định bạn còn lại cũng ~3h/ngày. Nếu ít hơn → cắt Sprint 4, xem ghi chú cuối.)*
- **Sprint 1 tuần** → **4 sprint = 4 tuần = 1 tháng**.
- Mục tiêu 1 tháng: **MVP dành cho học viên** chạy được (đăng nhập, flashcard, đề thi,
  báo cáo, nhiệm vụ). Khu quản trị giáo viên ở mức **tối thiểu**.
- Chia việc theo **lát cắt tính năng**: mỗi sprint làm trọn BE + FE cho mảnh đó.

## Nguyên tắc

- Mỗi sprint có **Definition of Done (DoD)** rõ, cuối tuần **chạy được + xem được**.
- Cuối mỗi sprint **cho cô giáo dùng thử**, ghi lại góp ý.
- Quản lý việc bằng **GitHub Issues + Projects** (Todo / Doing / Done).
- Một người thiên BE (bạn), một người thiên FE — hoặc chia đôi mỗi lát cắt.
- **Tuần 2 (Đề thi) là nặng nhất** — dồn lực, đừng để việc khác chen vào.

---

## Sprint 0 — Hạ tầng ✅ (ĐÃ XONG)

Docker: BE (Laravel 12) + FE (Next.js) + MySQL + Redis; 3 repo + submodule; tài liệu setup.

---

## Sprint 1 (Tuần 1) — Nền tảng + Flashcard

Cắm domain, đăng nhập thật, và làm trọn mảng Từ vựng.

- **BE:** merge domain (Models/migrations/controllers) vào Laravel 12, migrate.
  Auth API (`register/login/me/logout`) chạy. Deck/Card API + seed 2–3 bộ từ.
- **FE:** login nối API thật (lưu token, chặn route). Thư viện → Từ vựng → danh sách bộ
  → **học thẻ** (lật thẻ, phát âm, trước/tiếp), lưu tiến độ.
- **DoD:** đăng nhập bằng tài khoản seed; học trọn một bộ flashcard end-to-end, tiến độ lưu.

## Sprint 2 (Tuần 2) — Đề thi (trọn vẹn) 🔥

Lát cắt lớn nhất, làm gọn trong 1 tuần vì quỹ thời gian đã đủ.

- **BE:** API chi tiết đề (Part/Section/Question, ẩn đáp án), tạo attempt, lưu câu trả lời,
  `submit` + **chấm tự động (MCQ)**, API kết quả kèm lời giải. Seed 1–2 đề (~10–20 câu).
- **FE:** danh sách đề → trang giới thiệu (thời lượng, số câu) → **làm bài** (chọn đáp án)
  → nộp → **trang kết quả** (điểm, số câu đúng, tô xanh/đỏ, lời giải từng câu).
- **DoD:** làm trọn một đề, nộp, ra điểm đúng, xem được review đáp án + lời giải.

## Sprint 3 (Tuần 3) — Lớp học + Báo cáo + Nhiệm vụ

- **BE:** API lớp/buổi/lộ trình; báo cáo tổng hợp (điểm TB, số bài, lượt làm, lịch sử);
  nhiệm vụ (được giao/tự chọn, đánh dấu xong). Ghi `activity_logs` khi làm bài/học thẻ.
- **FE:** trang Lớp học (lộ trình theo buổi, nút Vào học); Báo cáo (4 chỉ số + biểu đồ +
  lịch sử); Nhiệm vụ (danh sách + nút "thêm vào nhiệm vụ").
- **DoD:** vào lớp thấy lộ trình; dashboard hiện số liệu thật; nhiệm vụ tự chuyển trạng thái.

## Sprint 4 (Tuần 4) — Quản trị GV tối thiểu + Hoàn thiện + Deploy

- **BE:** CRUD tối thiểu cho giáo viên: tạo lớp, thêm học sinh, tạo/gán đề (form đơn giản).
- **FE:** vài màn quản trị cơ bản cho GV; polish UI toàn app; sửa bug tồn đọng.
- **Deploy thử** lên một server staging (VPS/Render...) để cô giáo truy cập online.
- **DoD:** cô giáo tự tạo được 1 lớp + gán 1 đề cho học sinh; app chạy được online.

---

## NGOÀI phạm vi 1 tháng (Backlog — làm tháng sau)

- **Luyện nói với AI** — cần khảo sát dịch vụ chấm phát âm (Azure/Google), có chi phí.
  Không đưa vào 1 tháng.
- **Xếp hạng (leaderboard)**, **gamification** (kim cương/xu).
- **Bài viết / tài liệu** (kho blog).
- **Quản trị GV đầy đủ** (soạn đề nhiều dạng câu, ngân hàng câu hỏi, thống kê lớp sâu).
- **CI/CD** tự động deploy.

---

## Rủi ro & phương án lùi

- **Điểm dễ trượt nhất: Sprint 2 (Đề thi).** Nếu tới giữa tuần 2 thấy đuối → giữ MCQ đơn
  giản, bỏ các dạng câu phức tạp (nghe/nối) sang backlog.
- **Nếu bạn kia có ít thời gian hơn giả định** → bỏ **Sprint 4** ra khỏi 1 tháng: giai
  đoạn đầu cứ **seed nội dung bằng code**, để khu quản trị GV sang tháng sau. Như vậy 1
  tháng vẫn xong được lõi học viên (Sprint 1–3), là phần cô giáo có thể cho học sinh dùng.
- Mỗi tuần review đúng hạn; việc dư **đẩy sang tuần sau**, không nhồi cho kịp rồi vỡ chất lượng.

## Khởi động Sprint 1 — làm ngay

- [ ] Push hết (BE, FE, con trỏ submodule ở infra).
- [ ] Tạo GitHub Issues cho từng đầu việc Sprint 1, gắn người phụ trách.
- [ ] Chốt ai BE / ai FE.
- [ ] Chạy prompt Claude Code cắm domain vào BE (việc đầu tiên).