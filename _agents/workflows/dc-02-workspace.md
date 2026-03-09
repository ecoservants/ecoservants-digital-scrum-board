---
description: Git workspace setup and conventions for DC-02 External Database Mode
---

# DC-02 Workspace Workflow

## Branch Info
- **Feature branch**: `feature/DC-02-external-database`
- **Base branch**: `main`
- **Issue**: [#3 — DC-02: External Database Mode](https://github.com/ecoservants/ecoservants-digital-scrum-board/issues/3)

## Plugin Root
All work is done inside:
```
app/public/wp-content/plugins/ecoservants-digital-scrum-board/
```

## Git Commands

### Sync with main before starting work
// turbo
```bash
git fetch origin main
git rebase origin/main
```

### Push changes
```bash
git push origin feature/DC-02-external-database
```

### Create PR when ready
```bash
gh pr create --base main --head feature/DC-02-external-database --title "Feat: DC-02 External Database Mode" --body "Closes #3"
```

## Existing Scaffolding (from DC-01)
The file `ecoservants-scrum-board.php` already contains partial external DB support on `main`:
- `db_mode` option (`local` / `external`) in `es_scrum_default_options()`
- `es_scrum_get_db()` — basic connection attempt with fallback
- `es_scrum_table_prefix()` — prefix resolver for external mode
- Settings fields for external DB credentials (host, name, user, password, table prefix)
- Sanitization callback for the options

## Open PRs to Be Aware Of
These PRs are in-flight and also target `main`. Be mindful of potential merge conflicts:
- **PR #72** — DC-05 REST API layer
- **PR #73** — DC-07 Sprint System
- **PR #74** — DC-10 Security Framework
- **PR #76** — DC-03 User Access & Role Integration
- **PR #77** — Subtasks
- **PR #78** — DC-36 Theme Customizer
- **PR #79** — DC-24 Offline Mode
