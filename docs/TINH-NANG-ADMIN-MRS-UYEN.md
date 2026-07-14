# Tính năng Admin tham chiếu — Anh ngữ Mrs Uyên

> Khảo sát **chỉ xem** (không tạo/sửa/xóa) trên  
> `https://admin.anhngumrsuyen.uup.vn` — tài khoản admin `Mrsuyen`.  
> Ngày khảo sát: 2026-07-13.  
> Mục tiêu: nắm map tính năng hệ thống gốc để làm lại phần cốt lõi (MVP), không clone hết.

---

## 1. Tổng quan hệ thống

Nền tảng vận hành trung tâm Anh ngữ (UUP): quản lý lớp, giao bài, kho đề/nội dung, kết quả làm bài, báo cáo chất lượng, tài khoản học viên/nhân sự, kinh doanh (tư vấn + bán gói), ví xu AI, cài đặt web.

**Nhóm menu sidebar**

| Nhóm | Mục |
|------|-----|
| **Vận hành** | Báo cáo thống kê → Báo cáo chất lượng Beta, Báo cáo vận hành, Xếp hạng · Các lớp học · Kết quả làm bài |
| **Nội dung** | Nội dung của tôi · Chương trình · Kho đề |
| **Mở rộng** | Tài khoản (Học viên / Nhân viên) · Kinh doanh · Cài đặt · Ví của bạn |

Ngôn ngữ UI: VI / EN / ZH. Có thông báo hệ thống + Profile / Sign out.

---

## 2. Báo cáo thống kê

### 2.1. Báo cáo chất lượng (Beta) — `/`

Dashboard chất lượng học tập.

**Chỉ số tổng quan** (lọc Học viên / Khách hàng / Tất cả; đơn vị Tuần/Tháng)

- Tài khoản hoạt động
- Bài hoàn thành
- Lượt làm bài
- Thời gian học
- So sánh với kỳ trước (%)

**Biểu đồ**

- Điểm số trung bình theo tuần/tháng
- Phổ điểm: 80–100% / 60–80% / 40–60% / 20–40% / 0–20%

**Báo cáo lớp học** (lọc GV, trạng thái đang/sắp diễn ra, cơ sở, khoảng thời gian)

- Tỷ lệ hoàn thành nhiệm vụ
- Số HV điểm &lt; 60%
- Số HV không hoạt động
- Điểm tuần trước

**Báo cáo giáo viên**

- Tổng số lớp / lớp đang diễn ra
- Điểm TB học viên
- Phân loại chất lượng HV: Tốt (≥80%) / Khá (40–&lt;80%) / Yếu (&lt;40%)

**Báo cáo học viên** & **Báo cáo bài kiểm tra** — bảng/chart theo kỳ.

### 2.2. Báo cáo vận hành — `/site/operation-report`

- **Nội dung được tạo**: Bài tập, Đề kiểm tra, Bài giảng, Từ vựng, Luyện nói, Tài liệu (lọc 1–12 tháng)
- **Hoạt động hệ thống**: lớp sắp/đang/đã kết thúc; HV / khách / nhân viên hoạt động; số SP bán + doanh số
- **Gói sử dụng**: hạn mức học viên, test đầu vào, dung lượng lưu trữ, hạn dùng; quota AI (Nói / Viết / Transcript / Tạo đề IELTS)

### 2.3. Xếp hạng — `/ranking/center`

Bảng xếp hạng **điểm chuyên cần** toàn trung tâm: thứ tự, tên HV, cấp độ, điểm chuyên cần. Có tìm kiếm.

---

## 3. Các lớp học — `/class-room`

### 3.1. Danh sách lớp

- Tìm theo tên lớp / GV; lọc nâng cao: GV chính, cơ sở, trạng thái (sắp diễn ra / đang / đã kết thúc)
- Thẻ lớp: tên, GV, số HV, trạng thái
- Hành động: Xem / Sửa / Xóa · nút **Thêm lớp học**
- Dữ liệu mẫu quan sát: Lớp 6–12, 12F, Grade 12 (đều “Đang diễn ra”)

### 3.2. Chi tiết lớp (vd Grade 12)

Tab chính:

| Tab | Chức năng |
|-----|-----------|
| **Trang chủ / Tiến trình** | Gắn **chương trình** học (vd “ĐỀ THI THỬ DGNL”); ghi chú cho HV + ghi chú nội bộ |
| **Giao bài** | Giao nội dung theo tiến trình; chọn chương trình; ghi chú; filter tiến độ HV |
| **Nhận xét** | Điểm danh từng HV (Đúng giờ / Muộn / Nghỉ) + nhận xét; tải xuống nhận xét |
| **Báo cáo** | Báo cáo chất lượng trong phạm vi lớp (filter kỳ) |
| **Học viên** | Danh sách HV trong lớp; Thêm (tạo nhanh / chọn có sẵn / nhập file); đặt lại mật khẩu; xóa hàng loạt; xuất Excel |
| **Cài đặt** | Tên lớp, ảnh, cơ sở dạy, GV chính/phụ, ngày bắt đầu–kết thúc |

---

## 4. Kết quả làm bài

### 4.1. Kết quả kiểm tra — `/exam-test`

Bảng bài làm (classify mặc định = kiểm tra):

- Tên đề, kiểu đề, tên/email HV, lớp
- Điểm overall + điểm theo kỹ năng
- Thời gian bắt đầu/kết thúc
- Lọc / tùy chọn cột / xem hướng dẫn

### 4.2. Kết quả bài tập — `/exam-test?classify=1`

Cùng cấu trúc bảng, phân loại **bài tập**.

### 4.3. Kết quả luyện nói — `/practice-speaking-ai`

Lịch sử luyện nói AI:

- Tên chủ đề, HV, email, lớp, thời lượng, thời gian
- Lọc theo ID / chủ đề / HV / lớp / ngày; xóa hàng loạt / theo bộ lọc

---

## 5. Nội dung của tôi — `/my-content`

Hub nội dung do trung tâm tự tạo/quản lý.

### 5.1. Bài tập & Đề kiểm tra

| Mục | Route | Ghi chú |
|-----|-------|---------|
| **Dạng tiêu chuẩn** | `/exam-question` | CRUD đề; tạo thủ công / import Word; thêm vào danh mục; đẩy lên kho; On/Off |
| **Dạng IELTS Simulation** | `/exam-question/index-idp` | Tương tự + **Tạo đề AI**, tạo thủ công (mới); lọc kỹ năng / loại phí / trạng thái |
| **AI live talk** | `/speaking-topic` | Chủ đề luyện nói AI; trình độ Foundation→Advanced; thêm vào danh mục |

**Chi tiết đề** (sau khi tải từ kho / trong kho đề của trung tâm) gồm:

- Thông tin: mã đề, kỹ năng (Nghe/Nói/Đọc/Viết/Từ vựng/Ngữ pháp/Math/Verbal/Hỗn hợp/**Đề combo**), thời gian, AI grading, giới hạn lượt làm
- Cách tính điểm: IELTS Academic/GT, TOEIC, theo số câu đúng, 0.5/0.25/0.2 điểm/câu, tùy chỉnh, thang 10/100
- Cài đặt: danh mục, hiện đáp án & lời giải, chống gian lận + số lần vi phạm, công khai, đề nổi bật, hiện điểm sau làm, đề đánh giá năng lực, chỉ nghe audio, mô tả, hình ảnh
- Cấu trúc: **Part → Section → Question** (tiêu đề, nội dung, tags, thứ tự, điểm, loại câu, âm thanh, giải thích)
- Hành động: Nhân bản / Sửa / Sửa (mới) / Xóa

### 5.2. Nội dung giảng dạy

| Mục | Route | Ghi chú |
|-----|-------|---------|
| **Bài giảng** | `/document/lesson?type=1` | Tạo bài giảng; lọc Ẩn/Hiện, Miễn phí/Trả phí/Học viên; thêm vào danh mục |
| **Tài liệu** | `/document` | CRUD tài liệu; cùng kiểu lọc phí/trạng thái |
| **Từ vựng** | `/deck` | Bộ từ (deck); số từ; On/Off; tạo bộ; thêm vào danh mục (vd GRADE 10–12 UNIT…) |

### 5.3. Bài viết — `/post`

CRUD bài viết (blog/tin); Ẩn/Hiện; xóa hàng loạt.

---

## 6. Chương trình — `/study-program`

Lộ trình gắn vào lớp (tiến trình học).

- Danh sách: tên, số lượng (buổi/mục), ngày tạo, trạng thái Ẩn/Hiện
- Thêm / Xem / Sửa / Xóa chương trình
- Ví dụ quan sát: **Lớp 12**, **Lớp 6-11**
- Khi vào lớp → chọn chương trình để giao bài theo tiến trình

---

## 7. Kho đề — `/store-exam-question`

Kho đề dùng chung (marketplace / thư viện đề).

- Lọc theo **kỹ năng**: Nghe, Nói, Đọc, Viết, Từ vựng, Ngữ pháp, Math, Verbal, Hỗn hợp, Đề combo
- Cột: thông tin đề (số câu, kỹ năng, loại Đề thi, dạng Tiêu chuẩn / IELTS Simulation, Free…), lượt đã làm
- Hành động: **Tải đề** về trung tâm / **Xem đề** nếu đã tải
- Phân trang nhiều trang (HSK, Reading VOL 7/8, IELTS SCA Listening/Reading/Writing…)

---

## 8. Tài khoản

### 8.1. Học viên — `/student`

- ~120 tài khoản (quan sát): loại **Học viên** / **Khách hàng**
- Cột: họ tên, loại, lớp, cơ sở, ghi chú, xu đã dùng, trạng thái (On/Off), hành động
- Thêm mới / Nhập file / Cộng xu / Xuất Excel
- Xóa đã chọn / xóa theo bộ lọc
- Lọc: họ tên, email, SĐT, loại, trạng thái (khóa / chưa kích hoạt / đã kích hoạt / hủy), ngày tạo, cơ sở, lớp

### 8.2. Nhân viên (giáo viên) — `/user`

- Quản lý tài khoản staff: username, họ tên, SĐT, email, phòng ban, bộ phận, trạng thái
- Vai trò: **admin** / **Giáo viên**
- Thêm mới + bộ lọc

---

## 9. Kinh doanh — `/business`

### 9.1. Tư vấn

- **Khách hàng đăng ký** (`/contact`): lead form — họ tên, SĐT, email, khóa học quan tâm, mô tả, ngày tạo; thêm / xuất / lọc

### 9.2. Bán hàng

| Mục | Route | Ghi chú |
|-----|-------|---------|
| **Danh sách sản phẩm** | `/package` | Tab Gói xu / Sản phẩm; kích hoạt gói xu để HV mua AI luyện nói qua UUP |
| **Đơn hàng** | `/order` | Mã đơn, HV, gói, tổng tiền, PTTT, trạng thái, ngày tạo |
| **Khóa học đã bán** | `/package-purchased` | HV, gói, trạng thái, thời gian kết thúc; xuất |

---

## 10. Cài đặt — `/config`

### 10.1. Cài đặt Web

- **Cài đặt Menu** (`/menu`): Menu chính / footer — tên, icon, link, menu cha, trạng thái, thứ tự.  
  Menu học viên quan sát: Khoá học, Nhiệm vụ, Lớp học, Thư viện, Báo cáo (và nhóm Đề thi / Tài liệu / Tin tức ở filter cha).
- **Cài đặt chung** (`/setting`): cấu hình key-value theo nhóm (Liên hệ, Export đề, Cấu hình tài khoản, Hình ảnh, Nổi bật, MXH, Màu sắc, Form tư vấn, Mục tiêu, **Cơ sở**, **Môn học**…) — hotline, email, website, giới thiệu…

### 10.2. Quản lý hoạt động

| Mục | Route | Ghi chú |
|-----|-------|---------|
| **Phân quyền** | `/user-role` | Vai trò (admin, Giáo viên) + gắn action |
| **Lịch sử hoạt động** | `/action-log` | Audit: nhân viên, vai trò, mục, hành động, thông tin đổi, thời gian |
| **Lịch sử cập nhật** | `/update-notification` | Changelog phiên bản sản phẩm UUP |
| **Lượt chấm AI** | `/report-request` | Log request AI: kỹ năng Nói/Viết; loại Speech/OpenAI/Transcript/Gemini Generate Exam; trạng thái Processing/Done/Error |

---

## 11. Ví của bạn — `/center-wallet/overview`

Hệ thống **xu** cho AI:

- Số dư xu
- Tab: Tổng quan / Nạp xu / Lịch sử giao dịch
- Giải thích: phân phối xu cho HV (AI luyện nói…); nhân sự dùng xu tạo đề AI; không đổi ngược tiền mặt
- Lối tắt: Nạp xu, Lịch sử, **Phân phối xu**, Liên kết bán (HV mua xu từ UUP)
- Biểu phí sử dụng xu

---

## 12. Map với MVP dự án anhngu (gợi ý ưu tiên)

| Có trên admin gốc | MVP 1 tháng (theo KE-HOACH-SPRINT) | Ghi chú |
|-------------------|-------------------------------------|---------|
| Auth + role GV/HV | Có (Sprint 1) | Sanctum |
| Deck / từ vựng | Có (Sprint 1) | `/deck` |
| Đề tiêu chuẩn + làm bài + kết quả | Có (Sprint 2) | 4 loại câu — xem PHAN-TICH-DE-THI |
| Lớp + giao bài + báo cáo HV | Một phần (Sprint 3) | Bỏ điểm danh/nhận xét phức tạp nếu thiếu giờ |
| Chương trình / lộ trình buổi | Một phần (Sprint 3) | |
| Báo cáo chất lượng đầy đủ | Tối thiểu (Sprint 3) | 4 chỉ số + lịch sử |
| IELTS Simulation / Tạo đề AI | Backlog | |
| AI live talk / ví xu | Backlog | |
| Kho đề marketplace | Backlog hoặc seed tĩnh | |
| Kinh doanh / đơn hàng / menu web | Ngoài phạm vi | |
| Phân quyền RBAC đầy đủ | Role đơn giản đủ | |

---

## 13. Lưu ý khảo sát

- Chỉ **đọc / quan sát** UI và endpoint GET; không bấm Lưu / Tạo / Xóa / Tải đề / Kích hoạt.
- Một số tab (Giao bài, Báo cáo lớp) load AJAX — nội dung mô tả dựa trên HTML/API trả về khi xem.
- Admin gốc là sản phẩm UUP đầy đủ; dự án `anhngu-*` làm lại **lõi học viên + GV tối thiểu**, không clone 1:1.
