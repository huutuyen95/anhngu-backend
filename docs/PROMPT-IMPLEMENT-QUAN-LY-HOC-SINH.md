# Quản lý học sinh — Đặc tả hành vi (Admin 2)

> Đi kèm `DESIGN-UI-CHI-TIET.md` mục *Admin 2* (layout + API + edge cases) và
> `DAC-TA-CHUC-NANG.md` STT2. **Phần 1** dưới đây là **hành vi bắt buộc từng nút** —
> implement đúng từng dòng. Ngày: 2026-07-29.

---

## Phần 1 — Bảng action (8 nhóm)

Ký hiệu: **API** = endpoint gọi · **Optimistic** = cập nhật UI trước, revert nếu lỗi ·
**Toast** = thông báo sonner · **Edge** = trường hợp biên phải xử lý.

### §1.1. Nút ở header (3 nút)

| Nút | Bấm vào | API | Kết quả UI |
|-----|---------|-----|------------|
| **Tải Excel mẫu** | Tải file mẫu import | `GET /students/import-template` | Trình duyệt tải `.xlsx`; không đổi state bảng. Lỗi → Toast đỏ. |
| **Import Excel** | Mở **wizard 3 bước** (§1.7) | — | Mở Modal Stepper. |
| **+ Thêm học sinh** | Mở **modal thêm** (§1.5) | — | Modal trống, focus field Họ tên. **Ẩn nút này** khi đang ở filter *"Đã xoá"*. |

### §1.2. FilterBar (ghi state vào URL)

Mọi thay đổi filter → cập nhật URL query (`q, classroom_id→class, is_active→status, trashed, sort, dir, page, per_page`) rồi refetch. F5/share giữ nguyên.

| Control | Hành vi |
|---------|---------|
| Ô tìm kiếm `q` | Debounce 350ms; tìm theo họ tên/email/SĐT; reset `page=1`. |
| Select **Lớp** | Lọc `classroom_id`; option "Tất cả lớp". |
| Select **Trạng thái** | `all / active / locked`; map `is_active`. |
| Toggle **Đã xoá** | `trashed=1` → gọi list `?trashed=1`; đổi UI sang chế độ thùng rác (§1.3 dòng cuối). |
| Nút **Xoá bộ lọc** | Chỉ hiện khi có filter; reset toàn bộ query về mặc định. |

### §1.3. Thao tác trong bảng (theo hàng)

| Thao tác | API | Optimistic | Toast / Edge |
|----------|-----|------------|--------------|
| Tick **checkbox** hàng | — | có | Cập nhật thanh bulk (§1.4); header checkbox thành ✓/indeterminate. |
| **Click hàng** (ngoài control) | — | — | Mở modal **Xem chi tiết** (read-only). |
| **Switch** trạng thái | `PATCH /students/{id}/status {is_active}` | **có** | Toast *"Đã khoá/mở"* + nút **Hoàn tác 5s** → gọi lại status cũ. Lỗi → revert + Toast đỏ. Đang lọc theo status: **chỉ đổi badge**, không tự bỏ hàng. |
| Nút **Sửa** | mở modal (§1.5) | — | Email disable. |
| Nút **Xoá** | `DELETE /students/{id}` | sau confirm | ConfirmDialog; nếu `in_progress_attempts_count>0` → nêu số bài + *"dữ liệu bài làm vẫn được giữ"*. Thành công → hàng biến mất, Toast + **Hoàn tác 5s** (gọi `restore`). |
| Nút **Phục hồi** (chế độ Đã xoá) | `POST /students/{id}/restore` | có | Toast *"Đã phục hồi"*. |
| Nút **Xoá hẳn** (chế độ Đã xoá) | `DELETE /students/{id}?force=1` | sau confirm gõ chữ | ConfirmDialog `requireText` = "XOA"; **không** hoàn tác được (ghi rõ). |
| Click **header cột** sortable | refetch `sort,dir` | — | Toggle asc/desc; mũi tên chỉ hướng. |
| **Pagination** | refetch `page,per_page` | — | Giữ selection theo id nếu còn trong trang. |

### §1.4. Thanh bulk (hiện khi chọn ≥1)

Hiển thị *"Đã chọn N"* + các nút:

| Nút | API | Edge |
|-----|-----|------|
| **Khoá** | `POST /students/bulk {action:'lock', ids}` | Optimistic badge; Toast tổng kết. |
| **Mở** | `POST /students/bulk {action:'unlock', ids}` | như trên. |
| **Đổi lớp** | mở **modal A2class** → `POST /students/bulk {action:'assign_class', ids, classroom_id, mode}` | Radio `mode`: **add** (giữ lớp cũ) / **move** (gỡ lớp cũ). Nhiệm vụ lớp cũ **không** huỷ. |
| **Xoá** | `POST /students/bulk {action:'delete', ids}` | ConfirmDialog `requireText` = **đúng số lượng** (vd gõ "3"); liệt kê từng HS + số bài đang làm. |
| **Bỏ chọn** | — | Clear selection, ẩn thanh. |

### §1.5. Modal Thêm / Sửa

| Field | Quy tắc |
|-------|---------|
| Họ tên* | required |
| Email* | required, unique (kể cả đã xoá mềm). **Kiểm trùng khi blur** → báo dưới field. **Disable khi Sửa**. |
| SĐT | optional, định dạng số |
| Ghi chú | textarea optional |
| Lớp | multi-select (HS thuộc nhiều lớp) |
| Mật khẩu (chỉ khi Thêm) | Nút **"Sinh mật khẩu"** → server sinh; hoặc để trống → server tự sinh khi lưu |

- **Thêm** → `POST /students`; **Sửa** → `PUT /students/{id}`.
- Modal có **dirtyGuard**: đóng khi có thay đổi chưa lưu → confirm.
- Thêm thành công → mở **panel mật khẩu tạm** (§1.6). Sửa thành công → Toast + đóng.

### §1.6. Panel mật khẩu tạm (sau khi tạo)

- Hiện mật khẩu **đúng 1 lần**, có nút **Copy** (Toast *"Đã copy"*).
- Nút **"Thêm tiếp học sinh"** → reset modal thêm, giữ panel lịch sử phiên.
- Hàng học sinh mới trong bảng **highlight nền brand-soft 2 giây** rồi mờ dần.
- Cảnh báo: *"Mật khẩu chỉ hiện một lần, hãy gửi cho học sinh ngay."* Reload là mất.

### §1.7. Wizard Import (3 bước)

| Bước | UI | Hành vi |
|------|-----|---------|
| **1. Tải lên** | `FileUpload` dropzone (kéo thả / chọn), hiện % + huỷ | Chỉ nhận `.xlsx`; sai định dạng/size → lỗi ngay. |
| **2. Xem trước** | `POST /students/import?dry_run=1` → bảng preview lỗi **từng dòng** (`row, name, email, class, status: ok/duplicate/error, reasons[]`) + summary 3 số; radio xử lý email trùng (bỏ qua / cập nhật). | **CHƯA ghi DB.** Nút "Quay lại" / "Import". Có nút **Tải danh sách dòng lỗi**. |
| **3. Kết quả** | `POST /students/import` (dry_run=0, transaction) → 3 ô số (thành công / trùng / lỗi) + nút **Xuất danh sách mật khẩu tạm**. | Đóng → refetch bảng. |

### §1.8. Năm trạng thái màn hình (bắt buộc đủ)

1. **Loading** — 8 row skeleton đúng hình dạng cột (không spinner toàn trang).
2. **Có dữ liệu** — bảng bình thường.
3. **Empty — chưa có HS** — minh hoạ + *"Chưa có học sinh nào"* + CTA **+ Thêm học sinh** / **Import Excel**.
4. **Empty — lọc không khớp** — *"Không tìm thấy học sinh phù hợp"* + nút **Xoá bộ lọc** (nội dung khác case 3).
5. **Chế độ Đã xoá** — banner đỏ thùng rác, hàng mờ 60%, cột hành động = Phục hồi / Xoá hẳn, ẩn *"+ Thêm học sinh"*.

---

## Phần 2 — Checklist nghiệm thu (map với DESIGN-UI-CHI-TIET §4 Admin 2)

1. Đổi filter → F5 → kết quả giữ nguyên (state ở URL).
2. Switch trạng thái → Toast có **Hoàn tác**; bấm → quay lại.
3. Import 2 dòng lỗi → báo đúng dòng + lý do; bước 2 DB **chưa** có bản ghi.
4. Xoá 3 HS → nút chỉ bật khi gõ đúng số **3**.
5. Xoá 1 HS → mất khỏi list, hiện ở *Đã xoá*, **Phục hồi** chạy.
6. Đổi lớp `add` → HS 2 lớp; `move` → chỉ lớp mới; nhiệm vụ lớp cũ còn nguyên.
7. Mật khẩu tạm: reload là mất.
8. Checkbox đã chọn có ✓; header có gạch ngang khi chọn một phần.
9. Học sinh gọi `/api/v1/students` → **403** (kiểm ở API, không chỉ UI).
