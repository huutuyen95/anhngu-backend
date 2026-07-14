# Tính năng phía Học viên — Anh ngữ Mrs Uyên (tham chiếu)

> Khảo sát site: `https://anhngumrsuyen.uup.vn` (UI v3).  
> Ngày: 2026-07-13.  
> Tài khoản thử: `uyenminh@gmail.com` — **automation không gắn được Vue login** (form reactive); đã xác nhận **Làm bài bắt buộc đăng nhập** (toast: “Vui lòng đăng nhập để làm bài”).  
> Phần browse guest + màn chi tiết đề + menu từ admin đã ghi nhận đầy đủ bên dưới.

**Phạm vi dự án mình:** chỉ cần HV học trong lớp do **giáo viên quản lý** — không clone marketplace / bán khóa / ví xu / AI live talk của UUP.

---

## 1. Shell chung (mọi trang)

| Thành phần | Mô tả |
|------------|--------|
| Top bar | Nút **Đề thi**, ô **Tìm kiếm nhanh**, ngôn ngữ (VietNam), **Đăng nhập** (guest) |
| Bottom nav (guest) | **Nhiệm vụ** · **Thư viện** |
| Bottom nav (đã login — theo cấu hình admin) | Nhiệm vụ · Lớp học · Thư viện · Báo cáo · (có thể thêm Khoá học) |
| Footer | Lối tắt Tin tức / Tài liệu / Đề thi · Liên hệ · hotline · email · “Upp.vn” |
| Popup | “Đang tải phiên bản…” / banner **Luyện nói với AI** |

**Brand CSS (site gốc):** `--color-global: #002854` · nút accent `#F5AC3D`.

---

## 2. Đăng nhập — `/v3/auth/login`

- Form: Email *, Mật khẩu *, Quên mật khẩu, Đăng nhập  
- Payload API thực tế: `{ username: email, password }` → `POST https://api.../auth/login`  
- Sau login → `/home` (hoặc `redirectBack` trong localStorage)  
- Có thể bật Google SSO / Đăng ký (theo setting trung tâm)  
- Nền: pastel + balloon (marketing)

---

## 3. Trang chủ / Nhiệm vụ — `/v3/home`

**Guest vẫn xem được.**

- Block **Nhiệm vụ gợi ý**: danh sách đề xuất (Đề thi / Luyện nói…) + nút **Làm ngay**  
  Ví dụ: “Đề 1 12”, “Test Web”, “Numbers 1-5”  
- **Tin tức nổi bật** + Xem thêm  
- Footer marketing + liên hệ  

Khi đã login (theo admin / sprint mình): nhiệm vụ gắn lớp, đánh dấu xong, thêm từ thư viện.

---

## 4. Thư viện

### 4.1. Hub Thư viện

Từ bottom nav **Thư viện** → các mục con (footer / hub):

| Mục | Route quan sát | Ghi chú |
|-----|----------------|---------|
| **Đề thi** | `/v3/library/tests` | Danh sách đề |
| **Tài liệu** | `/v3/library/documents` | File / video / game |
| **Tin tức / Bài viết** | `/v3/library/news` | Blog |
| Từ vựng (admin có `/deck`) | Thường nằm trong Thư viện khi login | Flashcard — **ưu tiên MVP** |

### 4.2. Đề thi — `/v3/library/tests`

- Tab/filter: **Tất cả** · **Đề IELTS** · **Đề TOEIC** · **Bộ lọc**  
- Mỗi thẻ: tên, kỹ năng (Nghe/Đọc/Hỗn hợp/Ngữ pháp…), mức phí (**Miễn phí** / **Học viên**), trạng thái **Chưa làm**  
- Phân trang nhiều trang  
- Click → chi tiết `/v3/library/tests/detail/{slug}-{id}`

### 4.3. Chi tiết đề (vd Midterm lớp 7)

- Tên đề, thời lượng (vd **45 Phút**), mức phí, tổng điểm  
- **Sao chép link** · **Thêm vào nhiệm vụ** · **Kiểm tra thiết bị** · **Làm bài**  
- Guest bấm Làm bài → redirect login + “Vui lòng đăng nhập để làm bài”  
- Một số đề hiện modal nhập mã / trả phí (gate)

### 4.4. Làm bài (sau login — mô tả luồng chuẩn)

Theo admin + PHAN-TICH-DE-THI:

1. Bắt đầu attempt → timer server-side  
2. Làm theo Part/Section/Question (MCQ / fill / select / upload)  
3. Nộp → trang kết quả: điểm, đúng/sai, lời giải  

*(Chưa chạy end-to-end trên browser vì chưa login được bằng automation.)*

### 4.5. Tài liệu — `/v3/library/documents`

- Filter: **Tất cả** · **Tài Liệu** · **Video** · **Game**  
- Item mẫu: “lý thuyết mạo từ” · Miễn phí · ngày đăng  

### 4.6. Bài viết — `/v3/library/news`

- Filter chuyên mục: Tất cả · Giải trí · Ôn luyện · Tin tức IELTS  
- Sắp xếp: Mới nhất / Cũ nhất  
- Danh sách bài + ngày  

---

## 5. Các màn cần login (có trên UUP, map MVP)

| Màn | Ý nghĩa | MVP mình |
|-----|---------|----------|
| **Lớp học** `/my-class` | Lộ trình buổi, giao bài của lớp | Có (Sprint 3) — gọn |
| **Báo cáo** `/report` | Điểm TB, số bài, lịch sử | Có — tối thiểu |
| **Khoá học** `/membership` | Gói bán / quyền truy cập | **Không** (ngoài scope) |
| Luyện nói AI | Speaking topic + xu | **Không** (backlog) |
| Ví / nạp xu | Thanh toán AI | **Không** |

---

## 6. Map cắt bỏ so với UUP (quan trọng)

UUP học viên = portal công khai lớn (marketplace đề, tin tức, bán khóa, AI, đa ngôn ngữ…).  

**Anh ngữ nội bộ chỉ cần:**

```
Login → (App)
  ├── Nhiệm vụ          # bài GV giao / tự chọn
  ├── Lớp học           # lớp đang học + lộ trình buổi
  ├── Thư viện
  │     ├── Từ vựng     # flashcard
  │     └── Đề thi      # đề được gán / public trong lớp
  └── Báo cáo           # tiến độ cá nhân
```

Không làm: Tin tức marketing, Tài liệu/Game marketplace, Đề IELTS/TOEIC catalog khổng lồ, AI live talk, ví xu, đăng ký công khai, Google SSO (trừ khi cô yêu cầu).

---

## 7. Ghi chú kỹ thuật quan sát

- SPA Vue 3; prefix `/v3/...`  
- API: `https://api.anhngumrsuyen.uup.vn`  
- Guest browse được home + library; **attempt** cần auth  
- Menu học viên cấu hình được từ admin (`/menu` — Nhiệm vụ, Lớp học, Thư viện, Báo cáo…)
