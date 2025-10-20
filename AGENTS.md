# Repository Guidelines

## Project Structure & Module Organization
Keep the PHP bootstrap in `app.php` focused on wiring and request dispatch. House controllers, HTTP abstractions, and persistence logic under `src/` (e.g., `src/Controller`, `src/Http`, `src/Persistence`). Serve the SPA from `public/index.html`; ship styles and scripts from `public/assets/`. Persist the SQLite database in `storage/httpcapture.sqlite`, mounted through the `storage_data` named volume defined in `compose.yml`. Container configuration lives in `Dockerfile`; avoid parallel stacks or ad-hoc runtime scripts.

## Build, Test, and Development Commands
Use Docker for all local work. `docker compose up --build` builds the PHP runtime image and starts the single `app` service (PHP built-in server on port 8080). `docker compose exec app composer install` installs Composer dependencies once you add or update them. `docker compose exec app composer test` runs the PHPUnit suite, while `docker compose exec app composer lint` executes `phpcs`. `docker compose down -v` resets the container and drops the `storage_data` volume.
The UI lists 10 captures per page by default; adjust `perPage` in `public/assets/app.js` to change or expose a selector.

Release automation is handled by `.github/workflows/release.yml`. Push a semantic tag (`v*.*.*`) after configuring `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN` repository secrets to publish the Docker image and GitHub release.

## Coding Style & Naming Conventions
Follow PSR-12 for PHP: 4-space indentation, strict types, and namespaces that mirror folder paths (e.g., `HttpCapture\Controller\CaptureController`). Stick to small, focused classes—push request parsing into `src/Http` and persistence concerns into `src/Persistence`. Keep UI scripts modular in `public/assets/app.js` and use SCSS-like naming conventions in `app.css`. If you add migrations or seed data, collect them under `database/` with snake_case filenames. Run `composer lint` (phpcs) before committing.

## Testing Guidelines
Store backend tests in `tests/Feature` and `tests/Unit` using PHPUnit; mirror namespaces with the code under test. Name test files `*Test.php` and state behaviour (`ApplicationTest.php`). Run them via `docker compose exec app composer test`. Cover request parsing (original IP handling), repository persistence, API responses, and deletion flows. Keep fixture payloads under `tests/fixtures` if/when you add them.

## Commit & Pull Request Guidelines
There is no established history yet, so adopt Conventional Commits (`feat: add request archive purge`). Keep changes scoped, reference issue IDs in the body, and include screenshots or terminal captures that demonstrate request capture flows, delete actions, and UI updates. Pull requests must describe schema changes, migrations, and data reset steps so reviewers can reproduce results. Tag any Dockerfile or compose modifications in an additional checklist item.

## Security & Configuration Tips
Never commit real webhook payloads or API keys. Store secrets in `.env` files excluded from Git and load them through Docker secrets for production. Sanitize stored request bodies before rendering, and double-check CORS rules when exposing the UI. Rotate SQLite files when debugging to avoid leaking sensitive payloads.
