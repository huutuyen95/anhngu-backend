# Hover card lớp — màn chọn lớp (wClassPick)

Micro-interaction khi hover/focus/nhấn trên card lớp ở `/classes`. Chỉ lớp trình bày —
KHÔNG đổi dữ liệu/layout. Dùng token DS (`--shadow-*`, `--color-*`), không hardcode hex.

## Trạng thái (khớp code)

| Trạng thái | Hiệu ứng |
|---|---|
| rest | `--shadow-sm`, không transform |
| hover cả card | `translateY(-4px)` + `--shadow-md`, viền đậm thêm 1 bước; transition 180ms |
| hover — mũi tên "Vào lớp" | icon `translateX(3px)` (chữ đứng yên) |
| hover — avatar mã lớp | `scale(1.04)` |
| nhấn (active) | `translateY(-1px)` + `--shadow-sm` |
| focus bàn phím | outline `2px solid var(--color-accent)` offset 2px + nâng như hover |
| lớp đã kết thúc (ended) | hover **chỉ** đổi shadow, KHÔNG translateY |

Viền hover: card kem `--color-divider → --color-accent-400`; card accent → `--color-accent-300`
(sáng hơn nền accent-500).

## Ràng buộc

- `@media (hover: hover)` bọc mọi hover → mobile (`hover: none`) không kẹt trạng thái hover, chỉ còn `:active`.
- `@media (prefers-reduced-motion: reduce)` → bỏ mọi `transform`, chỉ giữ đổi shadow + viền.
- Chỉ MỘT chuyển vị chính mỗi card (translateY của card); avatar/mũi tên là phụ ≤4px.
- Không easing nảy, không shadow màu/glow, không đổi màu nền khi hover. Mọi transition 150–200ms.
- Hover thuần trang trí — không để lộ nội dung mới.

## Triển khai

- CSS state-based ở `frontend/app/globals.css` (khối `.class-card`, `.class-card__code`,
  `.class-card__arrow`, modifier `--accent`/`--neutral`/`--ended`) — Tailwind không diễn đạt gọn
  `@media (hover)` + reduced-motion cho riêng transform.
- Card là `<Link>` (thẻ `<a>`) nên tự focus được (Tab tới), Enter kích hoạt; state gắn qua modifier
  class trong `frontend/app/(app)/classes/page.tsx`.

## Lưu ý vận hành

- Sửa `globals.css` mà không thấy đổi → Turbopack cache: `docker compose exec frontend rm -rf .next`
  rồi `docker compose restart frontend`.
