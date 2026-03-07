# Open Source Release Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make httpcapture a polished, professional open-source project with proper documentation, CI/CD, and community files.

**Architecture:** Create community standard files (LICENSE, CONTRIBUTING, etc.), rewrite README with badges and clear structure, add a CI workflow for PRs, and harden the existing release workflow.

**Tech Stack:** GitHub Actions, Docker, PHP 8.4, Composer

---

### Task 1: Create LICENSE file

**Files:**
- Create: `LICENSE`

**Step 1: Create the MIT license file**

```
MIT License

Copyright (c) 2025 Alexander Garzon

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

**Step 2: Add `license` field to composer.json**

Add `"license": "MIT"` after the `"description"` field.

**Step 3: Commit**

```bash
git add LICENSE composer.json
git commit -m "add MIT license"
```

---

### Task 2: Create community files

**Files:**
- Create: `CONTRIBUTING.md`
- Create: `SECURITY.md`
- Create: `CODE_OF_CONDUCT.md`

**Step 1: Create CONTRIBUTING.md**

Content should cover:
- How to report bugs (GitHub Issues)
- How to contribute code: fork, create branch, write tests, run `composer test` and `composer lint`, submit PR
- Development setup: `docker compose up --build`, open `http://localhost:8080`
- Coding standards: PSR-12, enforced by phpcs
- Required secrets for maintainers: `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`

**Step 2: Create SECURITY.md**

Content:
- Supported versions (latest release)
- Report vulnerabilities via GitHub Security Advisories (Settings > Security > Advisories > New)
- Do NOT open public issues for security vulnerabilities
- Expected response time: best effort

**Step 3: Create CODE_OF_CONDUCT.md**

Use Contributor Covenant v2.1 — the standard for open source projects. Attribution to https://www.contributor-covenant.org. Contact method: GitHub Issues.

**Step 4: Commit**

```bash
git add CONTRIBUTING.md SECURITY.md CODE_OF_CONDUCT.md
git commit -m "docs: add contributing guide, security policy, and code of conduct"
```

---

### Task 3: Add CI workflow for PRs

**Files:**
- Create: `.github/workflows/ci.yml`

**Step 1: Create CI workflow**

The workflow should:
- Trigger on `push` to `master` and on `pull_request`
- Run on `ubuntu-latest`
- Steps: checkout, setup PHP 8.4 with `mbstring, pdo_sqlite`, composer install, `composer lint`, `composer test`
- Job name: `test`

**Step 2: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "ci: add test and lint workflow for PRs and pushes"
```

---

### Task 4: Review and fix release workflow

**Files:**
- Modify: `.github/workflows/release.yml`

**Step 1: Review current release.yml**

Current issues to fix:
- Docker tags use `${{ secrets.DOCKERHUB_USERNAME }}` instead of hardcoded `agarzon` — this is actually fine, keeps it configurable via secrets
- The `softprops/action-gh-release@v1` is outdated — update to `@v2`
- Strip the `v` prefix from Docker tags so images are tagged `1.0.0` not `v1.0.0` (Docker convention)
- Add `platforms: linux/amd64,linux/arm64` for multi-arch builds (useful since many devs use ARM Macs)

**Step 2: Apply fixes to release.yml**

- Update `softprops/action-gh-release@v1` to `@v2`
- Add a step to extract version without `v` prefix: `echo "VERSION=${GITHUB_REF_NAME#v}" >> $GITHUB_OUTPUT`
- Update Docker tags to use the clean version: `agarzon/httpcapture:$VERSION` and `agarzon/httpcapture:latest`
- Add QEMU setup step and `platforms: linux/amd64,linux/arm64` to build-push action
- Add `cache-from` and `cache-to` for faster builds

**Step 3: Commit**

```bash
git add .github/workflows/release.yml
git commit -m "ci: update release workflow with multi-arch builds and latest actions"
```

---

### Task 5: Rewrite README.md

**Files:**
- Modify: `README.md`

**Step 1: Write the new README**

Structure:
1. **Header** — Project name as H1, one-line description
2. **Badges** — License (MIT), GitHub Release, Docker Pulls, PHP Version (^8.4)
3. **Quick Start** — 3 steps using Docker: `docker run -p 8080:8080 agarzon/httpcapture`, open browser, send requests
4. **How It Works** — 3-4 sentences: any HTTP request to any path (except `/` and `/api/*`) is captured and stored in SQLite, viewable in the real-time UI
5. **API Reference** — Table of endpoints (GET /, GET /api/requests, etc.) — reuse existing table
6. **Configuration** — Polling interval, storage persistence, request filtering, IP detection
7. **Development** — Docker compose setup, running tests, linting, project layout
8. **Contributing** — Link to CONTRIBUTING.md
9. **License** — MIT, link to LICENSE

Badge URLs:
- `https://img.shields.io/github/license/agarzon/httpcapture`
- `https://img.shields.io/github/v/release/agarzon/httpcapture`
- `https://img.shields.io/docker/pulls/agarzon/httpcapture`
- `https://img.shields.io/badge/php-%5E8.4-8892BF`

**Step 2: Commit**

```bash
git add README.md
git commit -m "docs: rewrite README for open-source release"
```

---

### Task 6: Final cleanup

**Step 1: Verify .gitignore is complete**

Ensure these are present: `vendor/`, `storage/*.sqlite`, `storage/*.db*`, `.phpunit.cache/`, `.DS_Store`, `node_modules/`, `.env`

**Step 2: Add `keywords` and `homepage` to composer.json**

```json
"homepage": "https://github.com/agarzon/httpcapture",
"keywords": ["http", "webhook", "capture", "debugging", "inspector"]
```

**Step 3: Commit**

```bash
git add .gitignore composer.json
git commit -m "chore: add project metadata and ensure .gitignore completeness"
```

---

### Task 7: Verify everything

**Step 1: Run tests**

```bash
docker compose exec app composer test
```

Expected: All tests pass.

**Step 2: Run linter**

```bash
docker compose exec app composer lint
```

Expected: No violations.

**Step 3: Review all new files exist**

```bash
ls -la LICENSE CONTRIBUTING.md SECURITY.md CODE_OF_CONDUCT.md .github/workflows/ci.yml
```

Expected: All files present.
