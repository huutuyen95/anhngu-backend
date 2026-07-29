# Đặc tả chức năng (BẢN CHỐT) — Website học tiếng Anh (Anh ngữ Mrs Uyên)

> **Nguồn:** Google Sheet *"DANH SÁCH CHỨC NĂNG Website Learn English"* — 2 tab:
> `Function - Screen - Teacher (ADMIN)` và `Function - Screen - Student (FRONTEND)`.
> **Ngày tổng hợp:** 2026-07-27.
>
> Đây là **bản CHỐT phạm vi với cô giáo** — là *nguồn sự thật (source of truth)* mới nhất.
> Khi mâu thuẫn với các file khảo sát cũ (`TINH-NANG-*`, `DESIGN-ADMIN-HOC-VIEN`,
> `PHAN-TICH-DE-THI`, `KE-HOACH-SPRINT`), **ưu tiên file này**. Các file cũ đã được gắn
> ghi chú "⚠️ CẬP NHẬT" trỏ về đây ở những mục đã đổi.

---

## 0. Tổng quan & luồng nghiệp vụ chính

Web dạy & học tiếng Anh cho **trung tâm 1 giáo viên** (tham khảo `anhngumrsuyen.uup.vn`).
Gồm 2 phân hệ:

- **ADMIN (cô giáo)** — Laravel backend + khu quản trị: quản lý học sinh, lớp học; quản lý
  nội dung (Đề thi / Từ vựng / Tài liệu / Bài giảng); giao bài vào buổi học; chấm bài;
  xem kết quả & báo cáo lớp; điểm danh + nhận xét theo buổi.
- **FE (học sinh)** — Next.js: đăng nhập tài khoản được cấp → nhận nhiệm vụ được giao →
  vào lớp theo buổi → làm đề (trắc nghiệm / nghe máy tự chấm; writing chờ chấm) → học từ
  vựng flashcard → tự luyện trong Thư viện → xem điểm, nhận xét, báo cáo cá nhân.

**Luồng chính:**

```
Cô soạn nội dung (tạo thủ công / import Word)
  → tạo lớp & thêm học sinh (tạo nhanh / chọn có sẵn / import Excel)
  → giao bài vào buổi học (giao ngay / hẹn lịch / bản nháp, kèm deadline)
  → học sinh nhận thông báo + thấy trong Nhiệm vụ
  → làm bài: trắc nghiệm/nghe chấm tự động ra điểm ngay; writing vào hàng chờ chấm
  → cô chấm điểm + nhận xét (hoặc AI chấm — xem mục 2)
  → học sinh nhận thông báo, xem lại bài
  → cô theo dõi qua Kết quả làm bài & Báo cáo.
```

---

## 1. Phạm vi đã chốt

**LÀM (chức năng cơ bản):**

- Cô: quản lý & giao bài. Học sinh: học & làm bài.
- **Đề thi có 3 DẠNG** (xem mục 2 — đã mở rộng so với bản đầu):
  1. **Trắc nghiệm** A/B/C/D + câu trả lời text → máy tự chấm.
  2. **Nghe (Listening)** → cô up đề + file audio + cài sẵn đáp án → máy tự chấm.
  3. **Writing** → cô ra đề + gợi ý, học sinh gõ trên web → chấm (cô tay hoặc AI).
- Quản lý đề = **1 grid CRUD duy nhất**; mỗi đề gắn category. **Category theo lớp, nhiều
  thư mục** (xem mục 2, thay đổi #4).
- Thư viện FE giữ 3 mục: **Đề thi · Từ vựng · Tài liệu**. Bài giảng chỉ đến học sinh qua
  giao bài trong lớp.

**KHÔNG LÀM (đã chốt bỏ):**

- Kho đề chung (marketplace, tải đề/đẩy đề lên kho), trang hub "Nội dung của tôi".
- Giao diện IELTS Simulation, đề đa cấp danh mục hệ thống.
- Bài viết (blog/tin tức), đa ngôn ngữ.
- Xu / ví / nạp tiền / kinh doanh, Ngọc / xếp hạng thi đua.
- Cài đặt chung kiểu key-value nhiều tab (chỉ giữ vài config cần thiết, hardcode/env).
- Đăng ký công khai (học sinh do cô cấp tài khoản), Google SSO.
- **Nhóm AI (luyện nói)** tách riêng — chỉ làm nếu chốt riêng nhu cầu + chi phí (mục 5).

---

## 2. ⚠️ THAY ĐỔI MỚI TỪ CÔ GIÁO (deltas so với phạm vi cũ)

> Trích từ cột *"Ghi chú của cô giáo"* / *"Phản hồi của cô giáo"* trong sheet. **Đây là phần
> quan trọng nhất cần cập nhật vào thiết kế** vì nó khác với các doc cũ.

| # | Thay đổi | Trước đây | Bây giờ (chốt) | Ảnh hưởng |
|---|----------|-----------|----------------|-----------|
| **1** | **Thêm dạng bài NGHE (Listening)** | Đã chốt *bỏ* đề nghe (`KE-HOACH-SPRINT`, `PHAN-TICH-DE-THI` để backlog) | **CÓ** bài nghe: cô up đề + file audio + cài sẵn đáp án; học sinh làm & máy check. Web cô đang thuê có hướng dẫn up dạng này. | Đề có `skill=listening`; câu hỏi gắn `audio_url`; test player phát audio. Đưa vào **cốt lõi**, không còn backlog. |
| **2** | **Tích hợp AI ChatGPT Plus chấm Writing + Speaking** | Đã chốt *đóng* chấm AI — cô tự chấm tay | Cô muốn tích hợp **tài khoản ChatGPT Plus của cô** để AI chấm bài **Writing** (và **Speaking** nếu làm nhóm AI). Cô đưa **tiêu chí**, AI chấm điểm + nhận xét. | Mở lại luồng chấm AI (tùy chọn) cho câu `upload`/writing. Cần: cấu hình API/khóa, prompt theo tiêu chí, vẫn cho cô sửa điểm. Xem mục 6.1. |
| **3** | **Writing: 1 đề + gợi ý, đoạn ≤ 150 từ, đếm từ** | Chỉ nêu "đề + hướng dẫn" chung chung | Mỗi đề writing = **1 đề bài + gợi ý**; học sinh viết **1 đoạn không quá 150 từ**; **cần đếm từ (word count)** trên màn làm bài; tiêu chí chấm để đưa cho AI. | Màn làm bài writing thêm bộ đếm từ + cảnh báo vượt 150. Cấu hình `word_limit` cho đề writing. |
| **4** | **Nhiều thư mục đề kiểm tra theo lớp** | Category 1 cấp, dùng chung toàn hệ thống | *"Trong mỗi lớp học sẽ tạo được nhiều thư mục đề kiểm tra để tiện up bài ktra theo nhu cầu."* | Category/thư mục đề **gắn theo lớp** (hoặc cho phép lọc theo lớp); 1 lớp có nhiều thư mục. Cần xem lại mô hình category (mục 6.2). |
| **5** | **Đề trắc nghiệm đa dạng kiểu + template hướng dẫn** | Import Word format chuẩn (Part→Section→Question) | Đề trắc nghiệm sẽ **đa dạng kiểu** — cô cần **template + hướng dẫn** để up được **TẤT CẢ các dạng đề** (đây là phần cô dùng **nhiều nhất**). | File Word mẫu + tài liệu hướng dẫn phủ đủ 4 loại câu (MCQ, fill, select, và đọc hiểu có passage). Ưu tiên cao. |
| **6** | **Giao diện FE sinh động hơn** | UI gọn, tối giản | *"E cần giao diện web sinh động hơn web cũ."* | Định hướng thiết kế FE: hiện đại, sinh động hơn bản tham khảo (nhưng vẫn gọn, không bê marketing UUP). Xem prompt design ở cuối. |
| **7** | **Thang điểm: thang 10, chia đều theo số câu** | "Chấm theo số câu đúng", chưa chốt thang | Chốt: **mỗi đề khác số câu → dùng thang 10, chia đều điểm cho các câu.** | `scoring_method = scale_10_even` (điểm/câu = 10 / tổng số câu). Đủ dùng, không cần nhiều thang. |
| **8** | **Đọc hiểu (passage) & đề nghe: tham khảo web thuê** | Chưa có đề đọc hiểu mẫu | Cô đã **up nhiều đề trên web cô đang thuê** (đọc hiểu + listening) → nhờ xem để chốt cách trình bày passage/audio trong file Word. | Trước khi khóa format import Word, **khảo sát các đề cô đã up trên web thuê**. |

**Tóm tắt tác động:** dạng đề mở rộng **2 → 3 (thêm Nghe)**; **chấm AI (ChatGPT Plus) được mở
lại** như một tùy chọn cho Writing/Speaking; **category đề gắn theo lớp**; **word count cho
writing**; **thang điểm chốt = thang 10 chia đều**; **FE cần sinh động hơn**.

---

## 3. Phân hệ ADMIN (cô giáo) — 14 màn hình

> Nguồn: tab *Function - Screen - Teacher (ADMIN)*.

| STT | Màn hình | Mô tả cốt lõi | Ghi chú dev / lưu ý |
|-----|----------|---------------|---------------------|
| 1 | **Đăng nhập (Admin)** | Cô đăng nhập khu quản trị. | Màn login riêng. **Không** có "Quên mật khẩu" — quên thì liên hệ dev cấp lại. |
| 2 | **Quản lý học sinh** | Bộ lọc (SĐT/email); tải Excel mẫu + import Excel; thêm đơn lẻ. Grid: STT · checkbox (xoá hàng loạt) · họ tên · lớp · ghi chú · trạng thái (enable/disable) · hành động (xem/sửa/xoá). **Xoá = xoá mềm.** | Import Excel: validate email trùng/thiếu cột/sai định dạng, báo lỗi từng dòng. Disable ≠ Xoá (disable chỉ khoá đăng nhập). |
| 3 | **Tạo lớp học** | Form: tên lớp (bắt buộc) · ảnh đại diện (chọn từ kho ảnh có sẵn / tải lên) · ngày bắt đầu · ngày kết thúc · Hủy/Lưu. | Bỏ GV chính/phụ + cơ sở (chỉ 1 cô). Validate ngày KT ≥ ngày BĐ. Kho ảnh: bộ seed sẵn + nút "Tải ảnh lên" ngay trong modal — không cần màn quản lý kho ảnh. |
| 4 | **Quản lý đề thi** (grid CRUD) | 1 grid duy nhất. Cột: tên đề · category · **dạng đề (Trắc nghiệm / Nghe / Writing)** · số câu · cờ "Hiện trong thư viện" (On/Off) · hành động (xem/sửa/xoá). Nút Tạo: **Tạo thủ công / Import Word** (format: Part→Section→Question; `A./B./C./D.`, `==fill`, `==essay`, `==DA` đáp án, `==LG` lời giải). Quản lý category (1 cấp) cạnh grid. Tìm kiếm + lọc category/dạng; thao tác hàng loạt (xoá, đổi category). | Parser Word: validate số câu khớp `==DA/==LG`, giữ định dạng gạch chân/in đậm, báo lỗi từng dòng, **PREVIEW trước khi lưu**. Đề writing: đề bài + gợi ý, không đáp án; cấu hình `word_limit`/thời gian. Cờ "Hiện trong thư viện" = quy tắc 1 của Thư viện; writing mặc định tắt. **Cần file Word mẫu cho cô tải.** **(Xem đổi #4, #5, #8.)** |
| 5 | **Quản lý bộ từ vựng** | Grid: tên bộ (vd *GRADE 10 UNIT 5*) · số từ · ngày tạo · hành động (On/Off · xem · sửa · xoá). Trong mỗi bộ: thẻ từ (từ · nghĩa · **IPA** · **audio phát âm** · ảnh minh hoạ · câu ví dụ). | Nên **import từ vựng hàng loạt bằng Excel**. Audio: upload theo từ hoặc TTS. |
| 6 | **Quản lý tài liệu** | Grid: thumbnail · tên · ngày tạo · hành động (On/Off · sửa · xoá) · danh mục · tìm kiếm. Nội dung: tiêu đề + văn bản/ảnh/video nhúng/file đính kèm. | Upload file (pdf/video/link) → tính dung lượng lưu trữ. On/Off quyết định hiện trong Thư viện FE. |
| 7 | **Quản lý bài giảng** | Logic như 1 bài post: tiêu đề + nội dung (văn bản/ảnh/video). CRUD giống tài liệu + On/Off + danh mục. Học sinh chỉ nhận qua giao bài (không có mục riêng trong Thư viện FE). | Dùng **chung module với tài liệu** (1 module, 2 type) để tiết kiệm. |
| 8 | **Các lớp học (danh sách)** | Card 2 cột: ảnh · tên lớp · số học viên · trạng thái ("Đang diễn ra"); menu 3 chấm Xem/Sửa/Xóa. Header: tìm kiếm theo tên + nút Thêm lớp. | Bấm card → chi tiết lớp (4 tab, STT 9–12). Trạng thái suy ra từ ngày BĐ–KT. Mã lớp tự sinh. |
| 9 | **Chi tiết lớp → tab Giao bài** | Panel trái *"Tiến trình học"*: danh sách buổi/chủ đề do cô tạo + thêm buổi. Khu chính: Ghi chú + Giao bài; chọn buổi → xem bài đã giao. Modal Giao bài: (trái) chọn nội dung — lọc loại (Đề thi/Writing/Từ vựng/Tài liệu/Bài giảng) + category + tìm kiếm, mỗi item nút Chọn; (phải) Lớp (sẵn) · Buổi (bắt buộc) · Hạn hoàn thành (date) · Lịch giao (Giao ngay / Lên lịch / Bản nháp) · Hoàn thành. | Giao bài = gán nội dung vào buổi. "Lên lịch" cần cron/scheduler. Giao xong tự bắn thông báo cho học sinh. |
| 10 | **Chi tiết lớp → tab Nhận xét** | Chọn buổi (panel trái). Mỗi học viên: Điểm danh (Đúng giờ / Muộn / Nghỉ) + ô Nhận xét tự do. Nút "Tải xuống nhận xét" (export) + tìm kiếm học viên. | Điểm danh + nhận xét gắn **theo từng buổi**. Export gửi phụ huynh — chốt định dạng (Excel/PDF). |
| 11 | **Chi tiết lớp → tab Báo cáo** | Dashboard của lớp: chỉ số tổng quan (Học viên hoạt động · Bài hoàn thành · Lượt làm · Thời gian học, so kỳ trước). Biểu đồ điểm TB theo tuần + phổ điểm (5 dải). Bảng báo cáo tiến trình theo buổi (đã giao / đã làm / % hoàn thành / % điểm). Bảng báo cáo học viên (tỷ lệ hoàn thành · lượt làm · thời gian · số bài <60% · buổi đi học · điểm tuần trước; sort, tìm, chọn kỳ). | Aggregate nặng → **tính trước bằng job định kỳ**. **CHỈ tính bài được giao** (không tính tự luyện). Dùng chung API aggregation với Báo cáo cá nhân FE. |
| 12 | **Chi tiết lớp → tab Học viên** | Danh sách trong lớp: # · checkbox · ID · họ tên (avatar) · email · hành động (Đặt lại mật khẩu / Xem / Xóa khỏi lớp). Thêm học viên 3 cách: Tạo nhanh / Chọn có sẵn / Nhập File. | "Chọn có sẵn" liên kết màn Quản lý học sinh (STT 2) — 1 học sinh thuộc nhiều lớp. Xóa ở đây = gỡ khỏi lớp, **không** xoá tài khoản. |
| 13 | **Kết quả làm bài** | Grid: # · checkbox · tên đề · dạng đề · tên/email học sinh · lớp · điểm · **Nguồn (Bài giao / Tự luyện)** · trạng thái (Đang làm / Tạm dừng / Đã xong / **CHỜ CHẤM**) · Bắt đầu/Kết thúc · hành động (Xem/Sửa/Xóa). Bộ lọc nhanh **"Bài chờ chấm"** (writing chưa chấm). Xuất Excel + bộ lọc đầy đủ. | Trạng thái "Đang làm/Tạm dừng" → **autosave** bài dở. Cột Nguồn = quy tắc 2 của Thư viện. Thang điểm: **thang 10 chia đều** (đổi #7). |
| 14 | **Chi tiết bài làm (xem + chấm)** | Tổng quan: học sinh · mã bài · tên đề · trạng thái · **phát hiện gian lận** (số lần + chi tiết) · thời gian · câu sai · lớp. Trắc nghiệm/Nghe: review từng câu — đáp án chọn/đúng/lời giải; điểm tự động, cô sửa được; nút "Chấm lại". Writing: hiện đề + bài viết → cô nhập điểm + nhận xét → Lưu → thông báo học sinh. Nút Xuất kết quả. | Màn cô chấm writing (hoặc **AI chấm** — đổi #2): học sinh nộp → tab Chờ chấm (STT 13) → mở chấm ở đây → Đã chấm. Gian lận: FE track thoát màn/đổi tab. Nhận xét hiển thị cho học sinh. |

**Admin đã chốt BỎ:** kho đề chung (+ tải/đẩy đề), hub "Nội dung của tôi", IELTS Simulation,
bài viết (blog), Xu/ví/kinh doanh, Ngọc/xếp hạng, cài đặt key-value nhiều tab.

---

## 4. Phân hệ FE — HỌC SINH — 12 màn hình

> Nguồn: tab *Function - Screen - Student (FRONTEND)*.

| STT | Màn hình | Mô tả cốt lõi | Ghi chú dev / lưu ý |
|-----|----------|---------------|---------------------|
| 1 | **Đăng nhập (FE)** | Email + mật khẩu (do cô cấp). **Có "Quên mật khẩu"** (gửi link reset qua email). Không tự đăng ký. | Login riêng, tách admin. "Quên mật khẩu" cần SMTP/mail — phụ thuộc hạ tầng. |
| 2 | **Hồ sơ cá nhân** | Học sinh tự cập nhật: họ tên · SĐT · ngày sinh · email (khoá) · giới tính · địa chỉ · link Facebook. Kèm đổi avatar, đổi mật khẩu, đăng xuất. | Email (ID đăng nhập) **không** cho đổi. |
| 3 | **Bố cục chung (layout)** | Sidebar trái: Nhiệm vụ · Lớp học · Thư viện · Báo cáo + panel người dùng. Header: tìm kiếm nhanh (phạm vi Đề thi/Từ vựng/Tài liệu) · chuông thông báo (drawer, "Đọc tất cả") · nút Báo lỗi. Footer: giới thiệu + lối tắt + liên hệ. | Thông báo tự sinh khi cô giao bài & chấm xong writing → cần notify admin→FE. **Cô yêu cầu: giao diện sinh động hơn web cũ (đổi #6).** |
| 4 | **Nhiệm vụ (trang chủ sau login)** | Banner. 2 tab: "Nhiệm vụ 7 ngày tới" / "Hoàn thành". Danh sách: thumbnail + tên · phân loại (Đề thi/Writing/Từ vựng/Tài liệu/Bài giảng) · nút trạng thái (Chưa làm → mở màn tương ứng). | Nhiệm vụ = nội dung cô giao, lọc theo deadline 7 ngày. Màn mặc định sau login. |
| 5 | **Lớp học của tôi + Chi tiết lớp** | Card: ảnh · tên lớp · trạng thái (Đang học) · giáo viên · nút "Vào học". Chi tiết: panel trái "Tiến trình học tập" (buổi + tiến độ); chọn buổi → nội dung được giao nhóm theo loại (Đề thi/Từ vựng/Tài liệu/Bài giảng) kèm tiến độ (vd 0/32 từ). | Mirror tab Giao bài của admin (khác góc nhìn). Tiến độ đồng bộ giữa màn học & màn lớp. |
| 6 | **Thư viện (tự luyện)** | Hub 3 mục + số lượng: Đề thi · Từ vựng · Tài liệu. Thư viện Đề thi: tab danh mục (category **do cô tạo**) + tìm kiếm + "Lịch sử làm bài"; item: tên · dạng · trạng thái học sinh (Chưa làm / điểm cao nhất / số lần) · nút Làm bài. Chi tiết đề: tên + "Kiểm tra thiết bị" + điểm cao nhất + Làm bài. | **3 QUY TẮC:** (1) đề chỉ hiện khi cô bật "Hiện trong thư viện"; (2) bài làm luôn ghi Nguồn (nhiệm vụ vs tự luyện) — báo cáo lớp chỉ tính bài giao; (3) thư viện cho làm lại nhiều lần (lưu lịch sử, điểm cao nhất), bài giao mặc định 1 lần. Đề writing mặc định **không** hiện thư viện. **Cô muốn: mỗi lớp tạo nhiều thư mục đề (đổi #4).** |
| 7 | **Màn làm bài (test player — trắc nghiệm / nghe)** | Render Part→Section→Question (khớp import Word): MCQ = radio A/B/C/D; text (`==fill`) = ô nhập; **câu nghe = có player audio**. Mỗi câu có bookmark. Sidebar phải: đồng hồ đếm ngược · thống kê Chưa/Đã trả lời/Đánh dấu · lưới số câu · Nộp bài. Cài đặt: cỡ chữ (S/M/L) + âm lượng. Rời trang bị chặn (confirm); **autosave** (Đang làm/Tạm dừng), có mã bài làm. Nộp → máy chấm ngay + review đúng/sai + lời giải (`==LG`). | Khối nặng nhất FE. Câu text so đáp án sau normalize (hoa/thường, khoảng trắng; nhiều đáp án phân tách `/`). Phát hiện gian lận (đếm thoát màn/đổi tab qua `visibilitychange`). Nhận tham số Nguồn. **Thêm câu Nghe (đổi #1): phát audio, có thể giới hạn số lần nghe.** |
| 8 | **Màn làm bài Writing** | Hiện đề + gợi ý của cô; **ô nhập văn bản** (gõ trực tiếp, không nộp ảnh); **đếm số từ**; autosave nháp; đếm ngược nếu giới hạn; Nộp (confirm). Sau nộp: "Chờ cô chấm". Khi chấm xong: nhận thông báo, xem điểm + nhận xét cạnh bài. | **Giới hạn ≤ 150 từ, cần word count (đổi #3).** Textbox thuần, autosave. Trạng thái: nộp → chờ chấm → đã chấm. **Có thể chấm bằng AI ChatGPT (đổi #2).** |
| 9 | **Học từ vựng (flashcard)** | Danh sách bộ: mỗi từ hiện word · nghĩa · IPA · phát âm · ảnh · đánh dấu đã học; progress x/n; "Bắt đầu học". Màn học: mặt trước ảnh+từ+IPA+audio; "Lật" → mặt sau nghĩa + ví dụ (từ khoá in đậm); "Tôi đã biết" / "Tiếp tục"; progress bar. | Cần audio (upload/TTS) + ảnh theo từ. Tiến độ đồng bộ về chi tiết lớp + báo cáo. |
| 10 | **Xem tài liệu / bài giảng** | Mở nội dung được giao (hoặc tài liệu trong Thư viện): bài post đơn giản — tiêu đề + văn bản/ảnh/video nhúng/file tải về. Ghi nhận đã xem/hoàn thành → tính tiến độ buổi. | Bài giảng & tài liệu dùng **chung 1 viewer** (khác type). |
| 11 | **Tra từ điển khi bôi đen** (xuyên suốt) | Bôi đen 1 từ tiếng Anh ở màn đọc (bài giảng/tài liệu/xem lại bài) → popup nghĩa tiếng Việt + phiên âm + phát âm cạnh vùng bôi đen. | Yêu cầu từ cô. Selection API. Từ điển **self-host** bộ Anh-Việt (StarDict/open data) + endpoint nội bộ; audio dùng SpeechSynthesis. **Tắt mặc định trong màn làm bài** (chống gian lận đề từ vựng), bật theo đề nếu cô muốn. (Mở rộng: "Lưu vào bộ từ vựng".) |
| 12 | **Báo cáo cá nhân** | Tabs: Tổng quan / theo lớp. 4 card 30 ngày (Điểm TB · Bài hoàn thành · Lượt làm · Thời gian học) + sparkline tuần. Chart "Phân tích kỹ năng" theo tuần. Tiến độ các lớp (x/y lộ trình). Bảng Lịch sử làm bài + Lịch sử hoạt động 7 ngày. | Trùng nguồn số liệu với Báo cáo lớp (admin) — **chung API aggregation**, khác scope (1 học sinh vs cả lớp). Phân biệt bài giao vs tự luyện. |

**FE đã chốt BỎ:** Ngọc/xếp hạng, Xu/ví/nạp tiền, Bài viết (blog), đa ngôn ngữ, giao diện
IELTS Simulation, Luyện nói (chuyển nhóm AI).

---

## 5. NHÓM TÍNH NĂNG AI — TÁCH RIÊNG (chỉ làm nếu chốt riêng nhu cầu + chi phí)

| Mã | Tính năng | Mô tả | Điều kiện |
|----|-----------|-------|-----------|
| **AI-1** | **Luyện nói với AI** (FE + Admin) | FE: danh sách chủ đề theo trình độ (Foundation→Advanced), màn hội thoại nói với AI, lịch sử luyện nói. Admin: quản lý chủ đề + xem lịch sử phiên nói. | Cần dịch vụ AI voice realtime (thu âm → nhận diện → phản hồi) — **chi phí theo phiên**, phải chọn NCC & báo giá trước. Nếu làm: giới hạn lượt/tháng thay cơ chế Xu. |
| **AI-2** | **Chấm bài AI** | Ban đầu ĐÃ ĐÓNG (cô tự chấm tay). **→ Cô đổi ý:** tích hợp **ChatGPT Plus của cô** để AI chấm **Writing + Speaking** (điểm + nhận xét theo tiêu chí cô đưa). | Xem mục 6.1. Cần chốt: cơ chế tích hợp (API key vs tài khoản Plus), prompt tiêu chí, luồng cô duyệt/sửa điểm sau khi AI chấm. |

---

## 6. Cô đã phản hồi — chốt chi tiết

### 6.1. Chấm AI (ChatGPT Plus)
Cô muốn dùng **tài khoản ChatGPT Plus của cô** để AI chấm **Writing và Speaking**, đưa ra điểm
+ nhận xét theo **tiêu chí cô cung cấp**. → Cần thiết kế:
- Cấu hình khoá/kết nối AI (lưu ý: ChatGPT Plus là gói web; chấm tự động cần **OpenAI API key**
  — hạng mục cần làm rõ với cô về chi phí/kỹ thuật).
- Với mỗi đề writing: lưu **rubric/tiêu chí**; gửi (đề + bài viết + tiêu chí) cho AI → nhận
  điểm + nhận xét → hiển thị ở màn chấm (STT 14) để cô **duyệt/sửa** trước khi lưu.
- Speaking chỉ áp dụng nếu làm nhóm AI (AI-1).

### 6.2. Category đề theo lớp
Cô cần **nhiều thư mục đề kiểm tra trong mỗi lớp**. → Xem lại mô hình: category có thể gắn
`classroom_id` (thư mục riêng theo lớp) hoặc cho lọc category theo lớp khi giao bài. Chốt
phương án khi thiết kế DB (ảnh hưởng `DESIGN-DATABASE.md`).

### 6.3. Đề Writing
- 1 đề = **1 đề bài + gợi ý**; viết **1 đoạn ≤ 150 từ**; **đếm từ** trên màn làm bài.
- Tiêu chí chấm → đưa cho AI (6.1).

### 6.4. Đề đọc hiểu / nghe
- Cô đã **up nhiều đề trên web đang thuê** (đọc hiểu có passage + listening) → **khảo sát các
  đề đó** trước khi khóa format Word & cách trình bày passage/audio.

### 6.5. Thang điểm
- **Thang 10, chia đều điểm cho số câu** (điểm/câu = 10 / tổng số câu). Đủ dùng cho mọi đề.

---

## 7. Ảnh hưởng tới data model & các doc khác

| Doc | Cần cập nhật |
|-----|--------------|
| `PHAN-TICH-DE-THI.md` | Listening **vào cốt lõi** (không backlog); chấm AI writing **mở lại** (ChatGPT); thang điểm `scale_10_even`; word count cho writing. |
| `DESIGN-DATABASE.md` | `tests.skill` gồm `listening`; category đề gắn theo lớp (6.2); đề writing có `word_limit` + `rubric`; đề có `ai_grading` + cấu hình AI; `test_attempts` có `source` (giao/tự luyện), `status` (đang làm/tạm dừng/xong/chờ chấm). |
| `KE-HOACH-SPRINT.md` | Bổ sung Listening & word count vào Sprint 2; chấm AI đưa vào nhóm AI/backlog có điều kiện; category theo lớp vào Sprint 3. |
| `DESIGN-ADMIN-HOC-VIEN.md` | Cập nhật: 3 dạng đề, category theo lớp, FE "sinh động hơn", màn chấm hỗ trợ AI. |

---

## 8. Các mục cần cô chuẩn bị / dev làm rõ thêm

- [ ] File Word **mẫu + hướng dẫn** cho cô, phủ **tất cả dạng đề trắc nghiệm** (gồm đọc hiểu
      có passage) — ưu tiên cao (đổi #5).
- [ ] Khảo sát các đề cô đã up trên **web đang thuê** (đọc hiểu + listening) để chốt format.
- [ ] Làm rõ với cô về **chấm AI**: dùng OpenAI API (có phí theo lượt) — vì ChatGPT Plus (web)
      không tự chấm trong hệ thống được. Chốt ngân sách + phạm vi (chỉ Writing hay cả Speaking).
- [ ] Chốt định dạng **export nhận xét** (Excel/PDF) — STT 10 admin.
- [ ] Chốt phương án **category đề theo lớp** ở tầng DB (6.2).
