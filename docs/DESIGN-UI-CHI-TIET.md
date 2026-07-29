# DESIGN UI CHI TIẾT — Anh ngữ Mrs Uyên

> **Nguồn sự thật về giao diện.** Bám `DAC-TA-CHUC-NANG.md` (chức năng) + phản hồi cô giáo
> (*"cần giao diện sinh động hơn web cũ"*). Ngày: 2026-07-29.
>
> Brand đã chốt: **Option 1 (cam ấm / nền kem)** — xem §2.1. Các hướng cũ (**Lumen** ocean/coral,
> **navy/amber** #002854, **navy+shadcn** #0a2a5e) đều là **LỊCH SỬ**, không dùng nữa.

## Mục lục
- §0. Quyết định & mâu thuẫn đã biết
- §1. Hiện trạng FE (cái gì tái dùng)
- §2. Design system (2.1 màu · 2.2 typography · 2.3 spacing/radius/shadow · 2.4 animation · 2.5 component · 2.6 trạng thái chuẩn)
- §3. Bản đồ 26 màn (index + độ ưu tiên sprint)
- §4. Đặc tả màn Sprint 1: **Admin 1 — Đăng nhập (Admin)** · **FE 1 — Đăng nhập** · **Admin 2 — Quản lý học sinh**

---

## §0. Quyết định & mâu thuẫn đã biết

Đọc kỹ trước khi code. Nếu phát hiện mâu thuẫn MỚI → **hỏi lại, không tự quyết**.

| Mã | Vấn đề | Quyết định chốt |
|----|--------|-----------------|
| **C1** | 5 nguồn brand lệch nhau (Lumen / navy-amber / navy-shadcn / Option 1). | **Option 1 (cam/kem)** thắng. §2.1 là chuẩn duy nhất. |
| **C2** | Xoá học sinh: `DAC-TA-CHUC-NANG.md` yêu cầu **xoá mềm**; `DESIGN-DATABASE.md` ghi không dùng soft delete. | Theo **DAC-TA** → dùng `softDeletes`. Cần chốt thời hạn giữ (đề xuất **30 ngày** rồi purge). |
| **C3** | PROMPT A gốc nói *"bỏ shadcn, không thêm thư viện UI"*, nhưng FE **đã cài sẵn shadcn** (`@base-ui/react`, `sonner`, `cva`, `lucide-react`). | **Giữ shadcn/base-ui làm nền primitive** (đừng rebuild `sonner`/`dialog`/`dropdown`), nhưng **re-theme toàn bộ token sang Option 1** và **restyle** Button/Input/Card sang ngôn ngữ hình khối Option 1 (§2.3). "Không thêm thư viện UI **mới**" vẫn giữ. |
| **C4** | Dark mode: `globals.css` hiện có block `.dark` + next-themes. | Sản phẩm **không có dark mode**. Gỡ `.dark`, không render ThemeToggle; giữ next-themes ở mức không hại hoặc bỏ. |
| **C5** | Login FE hiện redirect cứng `/library`; auth.tsx chưa điều hướng theo role. | Redirect **theo role**: teacher/admin → `/teacher`, student → `/missions` (§4). |

---

## §1. Hiện trạng FE (cái gì tái dùng)

Next.js **16** App Router + Tailwind **v4** + shadcn (base-ui). Đã chạy được login → API thật.

**TÁI DÙNG (không viết lại):**
- `lib/api.ts` — `api<T>()`, `ApiError{status,errors}`, token localStorage, xử lý 204. **Thêm** `lib/api/*.ts` theo resource (auth, students…), **không** fetch rải rác.
- `lib/auth.tsx` — `AuthProvider` + `useAuth()` (user/loading/login/logout). **Bổ sung**: điều hướng theo role sau login; interceptor 401 giữa phiên → về login + toast.
- `lib/utils.ts` — `cn()` (clsx + tailwind-merge).
- `components/ui/*` shadcn đã có: `button, input, label, card, badge, dialog, dropdown-menu, tabs, skeleton, avatar, sonner`. → **Re-theme + restyle**, không xoá.
- `components/layout/*` (`app-header, app-sidebar, app-footer`), `components/page-header.tsx` — refactor sang shell mới.

**PHẢI SỬA/BỎ:**
- `app/globals.css` — thay toàn bộ token bằng §2.1; **gỡ block `.dark`**, gỡ `font-family` cũ; nạp font Option 1.
- `app/login/page.tsx` — đang hardcode `bg-emerald-50`/`blue-950`, redirect cứng `/library`. Restyle theo §4 FE-1 + redirect theo role.
- Route group: hiện chỉ có `(app)/`. Thêm `(teacher)/` cho khu admin.

**Quy tắc tuyệt đối:** không hardcode hex trong component (`grep -r "#[0-9A-Fa-f]\{6\}" components app` phải sạch, trừ `globals.css`). Tiêu đề dùng `font-display`, body dùng `font-sans`.

---

## §2. Design system (Option 1)

### §2.1. Màu

Nạp vào `app/globals.css` (`:root`) rồi map vào `@theme inline` của Tailwind v4 để có class (`bg-surface`, `text-muted`…).

```css
:root {
  /* Brand — cam ấm */
  --color-brand:        #F2793B;
  --color-brand-bold:   #D65F27;   /* hover/pressed, viền bóng nút */
  --color-brand-soft:   #FDEBDD;   /* nền active nav, chip */
  /* Accent — vàng nắng */
  --color-accent:       #FFC94D;
  --color-accent-soft:  #FFF3D3;
  /* Nền & bề mặt */
  --color-bg:           #FBF7EA;   /* nền trang (kem) */
  --color-surface:      #FFFFFF;   /* card, sidebar, modal */
  --color-surface-alt:  #FDFBF3;   /* vùng nhấn nhẹ, header row */
  /* Viền */
  --color-border:       #EFE7D4;
  --color-border-strong:#E4DCC8;
  /* Chữ */
  --color-text:           #3A3330;
  --color-text-secondary: #8A8073;
  --color-text-muted:     #B5AC9C;
  /* Semantic */
  --color-success: #7FAB2A;  --color-success-soft: #F1F8DE;
  --color-danger:  #E5604C;  --color-danger-soft:  #FDE7E2;
  --color-warning: #E3AB2D;  --color-warning-soft: #FFF3D3;
  --color-info:    #56C2EE;  --color-info-soft:    #E4F5FD;
  /* Kỹ năng đề (badge dạng/skill) */
  --skill-reading:   #E5604C;  --skill-reading-soft:   #FDE7E2;
  --skill-listening: #56C2EE;  --skill-listening-soft: #E4F5FD;
  --skill-writing:   #7FAB2A;  --skill-writing-soft:   #F1F8DE;
  --skill-speaking:  #B06CD6;  --skill-speaking-soft:  #F1E6FA;
}
```

Contrast: chữ trên `--color-brand` phải ≥ 4.5:1 → dùng **trắng**. Không đặt chữ nhỏ muted trên nền accent vàng. Không dùng dark mode.

### §2.2. Typography

- Nạp qua `next/font/google`, **subset `"vietnamese"`**, áp ở `app/layout.tsx`:
  - **Baloo 2** (600/700/800) → `--font-display` — tiêu đề, tên app, số liệu lớn (vibe "sinh động").
  - **Quicksand** (400/500/600/700) → `--font-sans` — body, form, bảng.
- Thang cỡ: page title 28–32 / section 18–20 / body 14–16 / caption 12–13. Line-height ~1.5 body, ~1.25 title.
- Body mặc định `font-sans`; chỉ heading/`<h1..h3>` và con số nhấn dùng `font-display`.

### §2.3. Spacing / Radius / Shadow — ngôn ngữ hình khối

Thang spacing 4px: `4 · 8 · 12 · 16 · 20 · 24 · 32 · 40`.

| Phần tử | Radius | Ghi chú |
|---------|--------|---------|
| Nút, chip | **pill 999px** | + **bóng đặc** `0 3px 0 var(--color-brand-bold)` (với primary); `:active` dịch `translateY(2px)` + bỏ bóng. **KHÔNG** shadow mờ nhiều lớp. |
| Input | 14px | viền 1.5px `--color-border`, cao **44px** (admin) / **48px** (học sinh), focus ring 2px `--color-brand`. |
| Card | 20px | viền 1.5px `--color-border`, nền `--color-surface`. |
| Modal | 24–26px | nền surface, overlay `rgba(58,51,48,.45)`. |
| Badge/tag | pill | nền `*-soft`, chữ màu gốc. |

Bóng card khi hover: nhấc nhẹ `translateY(-2px)` + `0 8px 20px rgba(58,51,48,.08)`, transition 180ms. Nút **không** dùng bóng mờ — chỉ bóng đặc kiểu "khối".

### §2.4. Giới hạn animation

- Transition **120–200ms** (hover/màu/nút), modal 200–260ms ease-out. **Không** animation > 400ms, không bounce/glow/parallax.
- Vi tương tác "sinh động" cho phép: hover nhấc card, nút nhấn lún (active translateY), skeleton shimmer nhẹ, empty-state có minh hoạ, progress bar mượt, tick ✓ khi hoàn thành. **Không**: confetti toàn màn, mascot AI floating, FAB.
- **Bọc mọi transform/animation trong** `@media (prefers-reduced-motion: reduce)` → tắt transform, chỉ đổi opacity tối thiểu.

### §2.5. Component (primitive & tái dùng)

Đặt ở `components/ui/`. Mỗi file 1 component, props TypeScript rõ. Nền = shadcn/base-ui (C3), restyle theo §2.1–2.3.

**Nhóm nền (PR A):** `Button` · `FormField` · `Input` · `PasswordInput` · `Checkbox` · `Card` · `Toast`(sonner) · `Skeleton`.
- `Button`: variant `primary|outline|ghost|danger`, size `sm|md|lg`, `loading` (spinner + disable, **giữ nguyên width** để không nhảy layout), `iconLeft/iconRight`, `fullWidth`. Primary = nền brand + bóng đặc pill.
- `Checkbox`: chưa chọn = ô trắng viền `--color-border-strong`; đã chọn = nền brand + **dấu ✓ trắng**; header 3 trạng thái trắng / gạch ngang (indeterminate) / ✓.

**Nhóm bảng/form (PR B):** `DataTable` · `Pagination` · `FilterBar` · `Modal`(dirtyGuard) · `ConfirmDialog`(requireText) · `Switch` · `FileUpload`(dropzone + % + huỷ) · `Stepper` · `EmptyState` · `Badge/StatusBadge`.
- `DataTable`: `columns[{key,label,width,align,sortable,render}]` · `rows` · `loading` · `selectable`+`onSelectionChange` · `stickyHeader` · `empty` slot · `renderMobileRow` (đổi sang card list khi <768px). **Thiết kế để tái dùng cho 6 grid admin.**

### §2.6. Trạng thái chuẩn (mọi màn có dữ liệu phải đủ 5)

1. **Loading** — skeleton đúng hình dạng nội dung (bảng = 8 row skeleton theo cột), **không** spinner toàn trang / màn trắng.
2. **Empty (chưa có dữ liệu)** — minh hoạ + 1 câu + CTA primary tạo mới.
3. **Empty (lọc không khớp)** — **nội dung KHÁC** case trên: "Không tìm thấy…" + nút Xoá bộ lọc.
4. **Error** — banner đỏ nhạt trong khu nội dung + nút Thử lại; lỗi field đọc từ `ApiError.errors`.
5. **Success/Done** — toast gọn (sonner), badge trạng thái, tick.
+ **Phân quyền**: student vào khu teacher → trang 403 thân thiện + nút về khu của mình (guard client, **API vẫn check**).

---

## §3. Bản đồ 26 màn (index & ưu tiên)

Chi tiết từng màn viết dần theo sprint. Sprint 1 (§4) đã đầy đủ. Nguồn hành vi: `DAC-TA-CHUC-NANG.md`.

**Admin (14):**
| Mã | Màn | Sprint | Ghi chú |
|----|-----|--------|---------|
| Admin 1 | Đăng nhập (Admin) | **1** | §4 — không có Quên MK |
| Admin 2 | Quản lý học sinh | **1** | §4 — grid + import Excel + xoá mềm |
| Admin 3 | Tạo lớp học | 3 | form + kho ảnh |
| Admin 4 | Quản lý đề thi (grid CRUD) | 2 | 3 dạng đề, import Word, category theo lớp |
| Admin 5 | Quản lý bộ từ vựng | 1–2 | deck + thẻ từ |
| Admin 6 | Quản lý tài liệu | 2 | |
| Admin 7 | Quản lý bài giảng | 2 | chung module với tài liệu |
| Admin 8 | Các lớp học (danh sách) | 3 | card 2 cột |
| Admin 9 | Chi tiết lớp → Giao bài | 3 | modal giao bài |
| Admin 10 | Chi tiết lớp → Nhận xét | 3 | điểm danh theo buổi |
| Admin 11 | Chi tiết lớp → Báo cáo | 3 | dashboard lớp |
| Admin 12 | Chi tiết lớp → Học viên | 3 | |
| Admin 13 | Kết quả làm bài | 2–3 | có tab "Chờ chấm" |
| Admin 14 | Chi tiết bài làm (chấm) | 2–3 | chấm writing (+ AI tuỳ chọn) |

**FE học sinh (12):**
| Mã | Màn | Sprint | Ghi chú |
|----|-----|--------|---------|
| FE 1 | Đăng nhập | **1** | §4 — có Quên MK (theo env flag) |
| FE 2 | Hồ sơ cá nhân | 3–4 | email khoá |
| FE 3 | Bố cục chung (layout) | 1 | shell + bottom nav |
| FE 4 | Nhiệm vụ (trang chủ) | 3 | 2 tab |
| FE 5 | Lớp học + chi tiết | 3 | mirror Giao bài |
| FE 6 | Thư viện (tự luyện) | 2 | 3 quy tắc |
| FE 7 | Test player (trắc nghiệm/nghe) | 2 | nặng nhất |
| FE 8 | Làm bài Writing | 2 | word count ≤150 |
| FE 9 | Flashcard | 1 | |
| FE 10 | Xem tài liệu/bài giảng | 2 | viewer chung |
| FE 11 | Tra từ điển bôi đen | 2–3 | Selection API |
| FE 12 | Báo cáo cá nhân | 3 | |

---

## §4. Đặc tả màn Sprint 1

### Admin 1 — Đăng nhập (Admin) · `app/(teacher)/login/page.tsx`

**Mục đích:** cô giáo vào khu quản trị. **Không** có Quên mật khẩu (quên → liên hệ dev).

**Layout — split 2 cột:**
- Cột trái **560px** (`flex-shrink-0`), nền `--color-brand`: đỉnh có logo "AU" + tên app (chữ trắng, `font-display`); đáy có tiêu đề *"Khu vực quản trị"* (`font-display` 30–32px, trắng) + 1 dòng mô tả nhỏ.
- Cột phải `flex-1`, card trắng **400px** **căn giữa cả trục dọc + ngang**. ⚠ Bug đã gặp: wrapper thiếu `width:100%` làm card lệch trái — **phải** cho wrapper `flex items-center justify-center w-full`. Card radius 26px, padding 36px:
  - Tiêu đề *"Đăng nhập quản trị"* + phụ đề *"Dành riêng cho giáo viên"*.
  - `FormField` Email* → `PasswordInput` Mật khẩu* → `Checkbox` *"Ghi nhớ đăng nhập trên máy này"*.
  - `Button` primary `fullWidth` *"Đăng nhập"*.
  - Dòng cuối (muted): *"Không có tự đặt lại mật khẩu, vui lòng liên hệ dev."*
- **<1024px**: ẩn cột trái, card full-width max 400px, padding 24.

**Hành vi & trạng thái (đủ 6):**
- Submit bằng Enter; nút → loading, form disable.
- `401` → banner đỏ nhạt **trong card**: *"Email hoặc mật khẩu không đúng"* (không nói rõ sai cái nào).
- `422` → message dưới đúng field (đọc `ApiError.errors`).
- `403 account_locked` → *"Tài khoản đang tạm khoá, liên hệ cô giáo."* (khu admin: đổi thành liên hệ dev).
- `429` → *"Vui lòng thử lại sau 60 giây"* + disable nút kèm **countdown thật** (đọc `Retry-After`).
- Thành công: role `teacher|admin` → `/teacher`; nếu là `student` login nhầm cửa admin → `/missions` + toast *"Tài khoản học sinh — đã chuyển về khu học tập."*
- Đã có token hợp lệ mà vào trang login → tự redirect đúng khu.

### FE 1 — Đăng nhập (Học sinh) · `app/login/page.tsx` (restyle file có sẵn)

**Mục đích:** học sinh vào bằng tài khoản cô cấp. **Có** Quên mật khẩu (ẩn theo env flag nếu chưa có SMTP).

**Layout — mobile-first, 1 cột căn giữa:**
- Logo 56px + *"Chào mừng trở lại!"* (`font-display`) + *"Đăng nhập để tiếp tục học nhé"*.
- 2 field cao **48px** (Email, Mật khẩu); link *"Quên mật khẩu?"* căn phải (ẩn nếu `NEXT_PUBLIC_ENABLE_PASSWORD_RESET !== 'true'`).
- `Button` primary `fullWidth` 48px *"Đăng nhập"*.
- Chân trang (muted): *"Chưa có tài khoản? Liên hệ cô giáo để được cấp."*
- Nền `--color-bg` (kem), card trắng radius 24. **Không** balloon marketing.

**2 màn phụ:** `/forgot-password` (nhập email → EmptyState *"Đã gửi link vào email của em"* — **không** tiết lộ email tồn tại), `/reset-password` (token + mật khẩu mới).

**Hành vi:** như Admin 1 (401/422/429), thành công student → `/missions`; teacher/admin login ở cửa học sinh → `/teacher`. 401 giữa phiên → về login + toast *"Phiên đăng nhập đã hết."*

### Admin 2 — Quản lý học sinh · `app/(teacher)/students/page.tsx`

> Đặc tả **hành vi từng nút** ở `PROMPT-IMPLEMENT-QUAN-LY-HOC-SINH.md` **Phần 1** (bắt buộc theo từng dòng). Mục này là layout + API + edge cases.

**Layout (thứ tự dọc):** page header (H1 *"Học sinh"* + meta tổng số + 3 nút) → `FilterBar` → thanh bulk (ẩn khi chưa chọn) → `DataTable` → `Pagination`.

**Cột bảng:** `# · checkbox · Họ tên (avatar) · Email · Lớp (chip, 2 + "+n") · Ghi chú · Trạng thái (Switch) · Hành động (Xem/Sửa/Xoá)`. <1280px ẩn cột Ghi chú; 768–1023 cuộn ngang giữ cột đầu sticky; <768 đổi card list.

**Bảng API (prefix `/api/v1`, middleware `auth:sanctum` + `role:teacher|admin`):**

| Method | Route | Việc |
|--------|-------|------|
| GET | `/students?q&classroom_id&is_active&trashed&sort&dir&page&per_page` | Danh sách + lọc + sort + phân trang |
| POST | `/students` | Tạo (trả mật khẩu tạm **1 lần**) |
| PUT | `/students/{id}` | Sửa (email disable) |
| DELETE | `/students/{id}` | Xoá mềm; `?force=1` xoá hẳn |
| POST | `/students/{id}/restore` | Phục hồi |
| PATCH | `/students/{id}/status` | `{is_active}` |
| POST | `/students/bulk` | `{action:lock\|unlock\|delete\|assign_class, ids[], classroom_id?, mode?}` |
| POST | `/students/import?dry_run=1` | Preview (không ghi DB) / commit |
| GET | `/students/import-template` | Tải Excel mẫu |
| POST | `/students/{id}/reset-password` | Sinh mật khẩu tạm mới (1 lần) |

**Edge cases (không bỏ):**
- Filter/sort/page ghi vào **URL query** → F5 / share link giữ nguyên ngữ cảnh.
- Switch trạng thái: **optimistic** + toast có nút **Hoàn tác 5s**; lỗi → revert.
- Đang lọc theo trạng thái mà đổi switch: **chỉ đổi badge**, không tự loại hàng khỏi bảng.
- Xoá hàng loạt: `ConfirmDialog` yêu cầu **gõ đúng số lượng** mới bật nút; liệt kê từng HS kèm số bài đang làm.
- Xoá HS đang có bài dở: confirm nêu rõ số bài + *"dữ liệu bài làm vẫn được giữ"*.
- Email unique kể cả bản ghi đã xoá mềm; khi sửa thì bỏ qua chính nó.
- Import: lỗi theo **từng dòng** + lý do; nút tải file dòng lỗi; **chưa ghi DB** tới khi bấm Import ở bước 2 (bọc transaction).
- Mật khẩu tạm: hiện **đúng 1 lần**, có nút Copy; reload là mất (không API nào trả lại).
- Filter *"Đã xoá"*: banner đỏ thùng rác, hàng mờ 60%, hành động đổi thành Phục hồi/Xoá hẳn, ẩn nút *"+ Thêm học sinh"*.
- Empty phân biệt *chưa có dữ liệu* vs *lọc không khớp* (§2.6).

**Ràng buộc:** API qua `lib/api/students.ts`; DTO ở `lib/types/student.ts`. Responsive 1440/1280/768/375. Icon `lucide-react`.
