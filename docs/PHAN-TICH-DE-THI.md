# Phân tích Engine Đề thi (từ admin trang tham khảo)

Khảo sát trực tiếp khu admin `admin.anhngumrsuyen.uup.vn` (Kho đề + Bài làm).
Mục tiêu: hiểu cách họ thiết kế đề thi & bài thi để **làm lại phần cốt lõi**, không clone hết.

---

## 1. Phát hiện quan trọng nhất

Dù tên đề nghe rất "IELTS" (True/False/Not Given, Note/Table/Flow-chart Completion,
Map/Plan/Diagram Labeling...), **bên dưới hệ thống chỉ có 4 dạng câu lõi**. Các "dạng IELTS"
chỉ là cách trình bày/đặt tên, còn kỹ thuật thì quy về 4 loại này:

| # | Loại câu (Loại câu) | Bản chất | Dùng cho |
|---|---|---|---|
| 0 | **Multiple choice** (Nhiều lựa chọn) | Chọn 1 đáp án từ A/B/C/D | MCQ, và cả T/F/NG khi để 3 lựa chọn |
| 1 | **Fill in blank** (Điền câu trả lời) | Gõ text vào chỗ trống | Completion, điền từ |
| 2 | **Select** (Chọn câu trả lời) | Chọn từ danh sách cố định | Yes/No/Not Given, matching đơn giản |
| 5 | **Upload** (Upfile trả lời) | Nộp file (audio/ảnh) | Nói / Viết — chấm bằng AI hoặc GV |

Ngoài ra ở **cấp Part** có 2 chế độ hiển thị:
- **Mặc định**
- **Kéo thả vào ảnh Part** — kéo đáp án lên một ảnh (cho Map/Diagram/Plan Labeling).

→ **Kết luận:** engine không cần "hỗ trợ mọi định dạng IELTS". Chỉ cần **4 loại câu + 1 chế độ
kéo-thả-lên-ảnh**. Đây là tin rất tốt cho phạm vi 1 tháng.

---

## 2. Cấu trúc đề (Đề thi)

```
Đề (Exam)
├── Thông tin chính
│   ├── Kỹ năng: Đọc / Nghe / Nói / Viết / Hỗn hợp
│   ├── Thời gian làm (vd 90 phút)
│   ├── Cách tính điểm: "Chấm điểm theo số câu đúng" (có thể còn cách khác)
│   ├── Chấm điểm bằng AI: có/không (cho câu Upload - nói/viết)
│   ├── Mức phí: Miễn phí / trả phí
│   ├── Danh mục tiêu chuẩn, Mô tả ngắn, Hình ảnh
├── Part 1 (có "Nội dung" = đề bài chung + ảnh + chế độ hiển thị)
│   ├── Question 1 (content, tags, loại câu, âm thanh?, options A-D, đáp án đúng, lời giải)
│   ├── Question 2 ...
├── Part 2 ...
```

Mỗi **câu hỏi** gồm: nội dung, tags, **loại câu** (1 trong 4), **âm thanh** (cho câu nghe),
các đáp án (đáp án đúng được đánh dấu), và **lời giải** (tiếng Việt).

Chương trình đề phân theo: **IELTS, TOEIC, VSTEP, CEFR, HSK, Cambridge YLE** (chỉ là cách gom
nhóm/gắn nhãn, không đổi engine).

---

## 3. Bài thi / Bài làm (kết quả học sinh)

Mục "Kết quả làm bài" chia 3 loại: **Kết quả kiểm tra / bài tập / luyện nói**.

Mỗi bài làm ghi:
- Tên đề, **Kiểu đề** (Nghe / Đọc / **Đề combo** = nhiều kỹ năng),
- **Điểm overall** + **Điểm theo từng kỹ năng** (Nghe / Đọc / Viết / Nói),
- Thời gian bắt đầu / kết thúc, học sinh, lớp.

→ Có khái niệm **"Đề combo"**: một đề gộp nhiều kỹ năng, chấm điểm tách riêng từng kỹ năng
rồi tổng hợp overall.

---

## 4. So với data model mình đã thiết kế — cần chỉnh gì

Model hiện tại (`tests / test_parts / test_sections / questions / question_options`) **đã khá khớp**.
Cần bổ sung/điều chỉnh:

**Bảng `questions`** — cột `type` dùng đúng 4 giá trị: `multiple_choice`, `fill_blank`,
`select`, `upload`. (Bỏ các dạng tưởng tượng khác.)
- Thêm `audio_url` cấp câu hỏi (cho câu nghe) — hiện đang để ở section, nên cho phép cả 2 cấp.

**Bảng `test_parts`** — thêm:
- `display_mode` enum(`default`, `image_drag`) và `image_url` (cho kéo-thả-lên-ảnh).

**Bảng `tests`** — thêm:
- `skill` enum(`reading`,`listening`,`speaking`,`writing`,`mixed`).
- `is_combo` (bool) — đề đa kỹ năng.
- `scoring_method` (vd `by_correct_count`).
- `ai_grading` (bool) — có chấm nói/viết bằng AI không.

**Bảng `test_attempts`** — thêm:
- `attempt_category` enum(`test`,`exercise`,`speaking`) — 3 loại kết quả.
- Điểm theo kỹ năng: có thể thêm bảng `attempt_skill_scores(attempt_id, skill, score)` cho đề combo.

**Bảng `question_options`** — cho câu `select`: options là danh sách cố định (True/False/Not Given...).
Câu `fill_blank`: đáp án đúng lưu ở `answer_text` (chấp nhận nhiều đáp án đúng → cân nhắc bảng
`question_answers` nếu 1 chỗ trống có nhiều cách viết đúng).

---

## 5. Cốt lõi cần làm (1 tháng) vs Để sau

**LÀM (Sprint 2 — cốt lõi):**
- 3 loại câu tự chấm được: **Multiple choice, Fill in blank, Select**.
- Cấu trúc Part → Question, đề bài Part, lời giải từng câu.
- Chấm tự động theo số câu đúng, quy về thang điểm.
- Câu nghe: gắn `audio_url` + phát audio.

**ĐỂ SAU (backlog):**
- **Upload (nói/viết) + chấm bằng AI** — tốn kém, cần khảo sát dịch vụ. Bản đầu: cho nộp file,
  GV chấm tay.
- **Kéo-thả-lên-ảnh** (Map/Diagram Labeling) — UI phức tạp, ít dùng, làm sau.
- **Đề combo đa kỹ năng + điểm tách kỹ năng** — bản đầu làm đề đơn kỹ năng trước.
- Phân nhóm chương trình (IELTS/TOEIC/...) — chỉ là tag, thêm sau dễ.

→ Nếu chỉ làm 3 loại câu tự chấm + audio nghe, **đã cover phần lớn đề thật** của cô giáo mà
vẫn kịp trong Sprint 2.