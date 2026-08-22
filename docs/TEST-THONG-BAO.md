# Cách test Thông báo (Student)

Tài khoản demo: học sinh `hs2@example.com` · giáo viên `teacher@example.com` — mật khẩu `Test@2026`.
FE: http://localhost:3000 · mọi lệnh artisan chạy qua Docker: `docker compose exec backend php artisan ...`

## A. Test tự động (nhanh nhất)
```
docker compose exec backend php artisan test --filter=StudentNotificationTest
```
Kỳ vọng: 5 pass (list, lọc chưa đọc, mark 1 + all, không đọc được của người khác, giao bài → notify).

## B. Test nhanh qua giao diện (seed bằng tinker)
1. Tạo vài thông báo cho hs2:
```
docker compose exec backend php artisan tinker --execute='
$u=\App\Models\User::where("email","hs2@example.com")->first();
$u->notify(new \App\Notifications\MissionAssigned(1,"Lớp 12F",2,13,"Cô Uyên"));
$u->notify(new \App\Notifications\AttemptGraded(4,99,"Đề Reading",8.5,1,"Cô Uyên"));
echo "unread=".$u->unreadNotifications()->count();'
```
2. Đăng nhập hs2 → xem **chuông ở góc phải hiện badge số** (vd "2").
3. Bấm chuông → popover liệt kê thông báo (icon theo loại, thời gian, chấm chưa đọc).
4. Bấm 1 thông báo → được đánh dấu đã đọc + điều hướng tới link; badge giảm ngay.
5. Bấm **"Xem tất cả"** → trang `/notifications`: lọc Tất cả / Chưa đọc, phân trang, "Đánh dấu đã đọc tất cả".
6. Chờ ~60s hoặc chuyển tab rồi quay lại → badge tự cập nhật (polling + on-focus).

## C. Test end-to-end thật (4 loại sự kiện)
**1. Cô giao bài** — login teacher → Lớp học → chọn lớp có hs2 → Giao bài (chọn 1 nội dung, gửi ngay) →
   login hs2 → chuông có "Cô vừa giao bài mới".
**2. Bài đã chấm** — teacher → Kết quả làm bài → mở 1 bài "Chờ chấm" (writing/speaking) → nhập điểm + Lưu →
   hs2 nhận "Bài của em đã được chấm".
**3. Ghi chú buổi** — teacher → Lớp học → buổi học → sửa **Ghi chú** của buổi (khác nội dung cũ) → Lưu →
   học sinh trong lớp nhận "Cô có ghi chú mới cho buổi học".
**4. Sắp đến hạn** — đặt hạn cho 1 nhiệm vụ của hs2 trong 2 ngày tới rồi chạy lệnh:
```
docker compose exec backend php artisan tinker --execute='
$u=\App\Models\User::where("email","hs2@example.com")->first();
\App\Models\Mission::where("user_id",$u->id)->where("status","!=","done")->whereNotNull("class_session_id")
 ->latest("id")->limit(1)->update(["due_date"=>now()->addDay(),"deadline_notified_at"=>null]);'
docker compose exec backend php artisan notifications:deadline-soon
```
   → hs2 nhận "Sắp đến hạn nộp". (Thực tế lệnh này chạy tự động hằng ngày 07:00.)

## D. Test API trực tiếp (tuỳ chọn)
Lấy token hs2 rồi gọi:
```
GET  /api/v1/me/notifications?filter=all|unread
GET  /api/v1/me/notifications/unread-count      → {"count": n}
POST /api/v1/me/notifications/read-all          → {"updated": n}
POST /api/v1/me/notifications/{id}/read
```

## E. Reset để test lại
```
docker compose exec backend php artisan tinker --execute='
\App\Models\User::where("email","hs2@example.com")->first()->notifications()->delete();'
```
