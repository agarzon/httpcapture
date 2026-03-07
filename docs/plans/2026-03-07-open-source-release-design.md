# Open Source Release Design

## Goal

Make httpcapture ready for public open-source release with professional documentation, CI/CD, versioning, and community standards.

## 1. README.md — Complete rewrite

- Hero section with project name, one-liner, badges (license, Docker pulls, GitHub release, PHP version)
- Screenshot placeholder
- Quick Start with Docker (3-step copy-paste)
- "How it works" brief explanation
- API reference table
- Configuration section (polling, storage, filtering)
- Development section (tests, linting)
- Contributing & License links

## 2. Community files

| File | Purpose |
|---|---|
| `LICENSE` | MIT license, copyright agarzon |
| `CONTRIBUTING.md` | Fork, branch, test, PR guide |
| `SECURITY.md` | Vulnerability reporting via GitHub security advisories |
| `CODE_OF_CONDUCT.md` | Contributor Covenant v2.1 |

## 3. GitHub Actions

- Review `release.yml` — ensure it builds and pushes to `agarzon/httpcapture` on Docker Hub
- Add `ci.yml` for PR/push validation (tests + lint)
- Tests must pass before Docker build

## 4. Versioning

- Semantic versioning (v1.0.0, v1.1.0, etc.)
- Tags trigger release workflow
- Docker images tagged as `latest` + version (e.g., `agarzon/httpcapture:1.0.0`)

## 5. Docker

- Clean compose.yml for public use
- Document secrets (`DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`) in CONTRIBUTING.md
