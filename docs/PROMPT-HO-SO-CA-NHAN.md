# Trang "Hồ sơ cá nhân" (Học sinh) — FE STT2

## Backend
- Migration: `users` thêm `birthday, gender, address, facebook_url, password_changed_at` (avatar_url/phone đã có).
- `ProfileController` (`/api/v1`, `auth:sanctum` — học sinh + giáo viên đều dùng):
  - `GET /me` — hồ sơ đầy đủ + `classroom`, `student_code` (HS + id), `active_sessions_count`.
  - `PUT /me` — **bỏ qua email + role** (chỉ validate/fill name,phone,birthday,gender,address,facebook_url;
    tự prepend `https://` cho facebook_url).
  - `POST /me/avatar` (JPEG/PNG ≤2MB, crop vuông client + resize GD 400×400), `DELETE /me/avatar`.
  - `PUT /me/password` — kiểm current (422 field), mới ≥8 + có chữ&số + confirmed + khác cũ; **cấp token mới**
    trong response (giữ phiên, không bắt đăng nhập lại).
  - `POST /me/logout-others` — xoá mọi token trừ token hiện tại.
- Test: `ProfileTest` (11 ca) — email không đổi, phone/birthday 422, avatar >2MB 422, sai/trùng mật khẩu 422,
  đổi mật khẩu giữ phiên (token mới), logout-others chỉ còn 1 token.

## Frontend
- `app/(app)/profile/page.tsx` (form + avatar crop + bảo mật) và `app/(app)/profile/password/page.tsx` (checklist realtime).
- `features/profile/avatar-cropper.tsx` (crop vuông zoom+kéo → blob 400×400). `lib/api/profile.ts`, `lib/types/profile.ts`.
- Header avatar → link `/profile` (active nền accent). `useAuth().refreshUser()` cập nhật tên/avatar header sau khi lưu.

## Giả định đã chọn
- Design `Learn English Student.dc.html` (wProfile/wPass) KHÔNG có trong repo → dựng theo đặc tả + organic DS.
- **FIX phát sinh**: `components/student/student-shell.tsx` thiếu class `organic` bọc nội dung → mọi component
  class organic (`.btn/.field/.seg/.input`) trên trang học sinh không áp. Đã thêm `organic` vào root shell —
  sửa luôn CTA trang "Lớp của em" bị mất style.
- Ngày sinh dùng `<input type="date">` (hiển thị theo locale trình duyệt).
- Router-guard khi rời trang dirty: có `beforeunload` (đóng/reload) + confirm ở nút Đăng xuất; chưa chặn
  điều hướng client-side giữa các trang (App Router chưa có API guard gọn).
- `student_code` sinh dạng `HS####` từ id (chưa có field mã HS thật).
