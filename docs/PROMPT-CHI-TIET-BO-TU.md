# Học từ vựng TRONG LỚP (My Class) — chi tiết bộ từ + flashcard

Nội dung học nằm trong My Class, TÁCH BIỆT HOÀN TOÀN với Thư viện. Tiến độ từ vựng học trong lớp
scope theo `card_progress.classroom_id` (học ở lớp KHÔNG đụng tự luyện Thư viện, và ngược lại).

## Backend
- `card_progress` thêm cột `classroom_id` (nullable). Học trong lớp = classroom_id lớp; Thư viện = NULL.
  Unique đổi thành (user_id, card_id, classroom_id). Migration `2026_08_15_000000`.
- `StudentDeckController` (`decks/{deck}/study`, `cards/{card}/progress`, `decks/{deck}/session-complete`)
  nhận `classroom_id`: có → xác thực HS thuộc lớp (403 nếu không), đọc/ghi tiến độ theo scope,
  và CHỈ đánh dấu mission 'done' của lớp khi học TRONG LỚP (≥ `content.deck_complete_pct`, mặc định 80%).
  `study` trả thêm `progress:{known,total}` theo scope.
- `StudentRoadmapService::deckProgress` đếm known WHERE classroom_id = lớp (không lẫn Thư viện).

## Frontend (route trong lớp, KHÔNG /library)
- Màn D — chi tiết bộ từ: `classes/[classId]/vocab/[deckId]/page.tsx` (đọc `?session`). Lưới thẻ lật 3D
  tại chỗ (front: ảnh + từ + "(Nghĩa Viết Hoa)" + IPA + "Nghe từ"; back: + `Ví dụ : "..."` term in đậm
  + "Nghe cả câu"; chỉ báo ✓ đã thuộc / số). Nút "Bắt đầu học" → màn B.
- Màn B — flashcard: `classes/[classId]/vocab/[deckId]/study/page.tsx` → `<DeckStudy classroomId .../>`.
- Component tái dùng: `features/vocabulary/{deck-study,speak-button}.tsx`,
  `features/documents/document-viewer.tsx`. Trang Thư viện `library/vocab|documents/[id]` render
  cùng component (không classroomId, backHref về /library) — Thư viện không đổi hành vi.
- CTA lộ trình (`features/classes/roadmap-helpers.ts`): deck → `/classes/{id}/vocab/{deckId}`;
  document/lecture → `/classes/{id}/documents/{id}`. KHÔNG còn href '/library/' trong luồng học của lớp.

## Seed
`ClassVocabDemoSeeder` (idempotent): 1 buổi có 2 bộ từ (nhiều nhóm) giao cho hs2@example.com.
`php artisan db:seed --class=ClassVocabDemoSeeder`.

## Test
`VocabularyTest`: học trong lớp ≥80% → mission done; tự luyện Thư viện KHÔNG hoàn thành mission lớp.
`StudentRoadmapTest`: tiến độ deck trong lộ trình chỉ đếm scope lớp (không lẫn Thư viện).
