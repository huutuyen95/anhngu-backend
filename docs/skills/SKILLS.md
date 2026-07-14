# Skills — Backend (Anh ngữ)

| Thư mục | File | Dùng khi |
|---------|------|----------|
| `anhngu-laravel-backend` | [SKILL.md](anhngu-laravel-backend/SKILL.md) | API Laravel + Architecture (Form Request, Service, Repository) |

Skill frontend nằm ở `frontend/docs/skills/`.

## Cách bật trên máy (Cursor)

Từ root `anhngu-infra`:

```bash
mkdir -p .cursor/skills
ln -sfn ../../frontend/docs/skills/anhngu-frontend-ui-ux .cursor/skills/anhngu-frontend-ui-ux
ln -sfn ../../backend/docs/skills/anhngu-laravel-backend .cursor/skills/anhngu-laravel-backend
```

> **Nguồn thật** = `backend/docs/skills/` (skill API) và `frontend/docs/skills/` (skill UI). Sửa ở docs rồi commit trong submodule tương ứng.
