# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Development
docker compose up --build              # Start dev server on port 8080
docker compose exec app composer test  # Run PHPUnit test suite
docker compose exec app composer lint  # Run phpcs (PSR-12)
docker compose down -v                 # Stop and reset storage volume

# Run a single test
docker compose exec app vendor/bin/phpunit --filter testMethodName
```

## Architecture

httpcapture is a zero-dependency PHP 8.4 HTTP capture tool with a Vue 3 SPA frontend. Any request to a non-reserved path is stored in SQLite and displayed in real-time.

**Request lifecycle:** `public/index.php` → `app.php` (wiring) → `Application::handle()` → Router matches or CaptureController stores.

**Backend (`src/`):**
- `Application.php` — Orchestrator: sets up router, dispatches requests, falls through to capture
- `Http/Router.php` — Regex-based pattern matching with `{param}` support, handlers return `Response` or `null`
- `Http/Request.php` — Wraps PHP globals, normalizes files, resolves client IP (Cloudflare → X-Forwarded-For → REMOTE_ADDR)
- `Http/RequestFilter.php` — Chainable builder to ignore paths/prefixes/extensions (favicon, robots.txt, etc.)
- `Controller/CaptureController.php` — Stores captured requests with scheme detection
- `Controller/RequestsController.php` — REST API: list (paginated), show, delete one/all
- `Persistence/DatabaseConnection.php` — PDO/SQLite wrapper, auto-creates schema and adds missing columns
- `Persistence/RequestRepository.php` — CRUD with pagination, JSON-encodes complex fields (headers, query_params, form_data, files)

**Frontend (`public/`):**
- `index.html` — Vue 3 SPA loaded via CDN (Vue, Tailwind, highlight.js)
- `assets/app.js` — Vue composition API app, polls `/api/requests` every 5s
- `assets/app.css` — Custom styles with glassmorphism, animations, hljs theme

**API routes** (defined in `Application.php`):
- `GET /api/requests` — Paginated list (`page`, `per_page` params)
- `GET /api/requests/{id}` — Single capture
- `DELETE /api/requests/{id}` — Delete one
- `DELETE /api/requests` — Delete all
- `GET /` — Serves the SPA
- `* (anything else)` — Captured and stored

## Testing

Tests live in `tests/Feature/ApplicationTest.php`. Each test creates a temporary SQLite DB and tears it down after. Tests cover: capture + list, frontend routing, deletion, pagination, multipart forms, GET fallback, IP detection, and request filtering.

## Conventions

- PSR-12 coding standard (enforced by phpcs)
- PSR-4 autoloading: `HttpCapture\` → `src/`
- Conventional Commits: `feat:`, `fix:`, `docs:`, `ci:`, `chore:`
- No external PHP dependencies — native PDO, built-in server
- Release by pushing a semver tag (`v1.0.0`) — triggers GitHub Actions to test, build multi-arch Docker image, and publish to Docker Hub
