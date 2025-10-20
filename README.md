# httpcapture

Capture, inspect, and replay incoming HTTP requests to debug webhooks and third-party integrations.

## Quick Start
- Build and run the container: `docker compose up --build`
- Open [http://localhost:8080](http://localhost:8080); send any HTTP request to the same host/port to capture it.
- Stop and clean up: `docker compose down -v`

## Project Layout
- `public/` – Vue-powered UI and router entry point (`index.html`, `assets/app.{css,js}`)
- `src/` – PHP application code (router, controllers, persistence)
- `storage/` – SQLite database (`httpcapture.sqlite`, ignored from Git)

## Core Endpoints
| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/` | Single-page UI |
| `GET` | `/api/requests` | List captured requests (latest first) |
| `GET` | `/api/requests/{id}` | Retrieve a single request |
| `DELETE` | `/api/requests/{id}` | Delete an individual capture |
| `DELETE` | `/api/requests` | Purge all captures |
| `*` | Any other path | Captured and stored automatically |

Original IP detection prioritises `X-Forwarded-For`, falling back to `REMOTE_ADDR`.

## Development Workflow
1. Install PHP dependencies inside the container: `docker compose exec app composer install`
2. Run tests: `docker compose exec app composer test`
3. Lint code: `docker compose exec app composer lint`

List endpoints accept `page` (1-based) and `per_page` (max 100) query parameters; the UI shows 10 per page by default with pager buttons at the top of the list.

## Release Automation
Pushing a tag matching `v*.*.*` triggers the GitHub Actions release workflow:

- Runs the PHPUnit suite
- Builds and pushes the Docker image to Docker Hub as `latest` and the tag version
- Generates release notes and creates a GitHub Release

Configure the following repository secrets before tagging:

- `DOCKERHUB_USERNAME`
- `DOCKERHUB_TOKEN`

## Configuration
- Captured requests persist inside the Docker volume `storage_data`; remove it with `docker compose down -v` when you need a clean slate.
- UI refreshes the request list every 5 seconds; adjust polling in `public/assets/app.js` if needed.
- For production, front the container with HTTPS termination and persist `storage_data` to durable storage.
