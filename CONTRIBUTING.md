# Contributing to HTTPCapture

Thanks for your interest in contributing! We welcome all kinds of contributions -- bug reports, feature requests, and code.

## Reporting Bugs

Please open a [GitHub Issue](../../issues) with a clear description of the problem, steps to reproduce it, and the expected vs. actual behaviour.

## Contributing Code

1. **Fork** the repository and clone your fork locally.
2. **Create a feature branch** from `master`:
   ```bash
   git checkout -b feature/my-change
   ```
3. **Write tests** for your changes.
4. **Run the quality checks** before submitting:
   ```bash
   composer test
   composer lint
   ```
5. **Submit a pull request** against `master` with a clear description of your changes.

## Development Setup

Start the development environment with Docker:

```bash
docker compose up --build
```

The application will be available at **http://localhost:8080**.

## Coding Standards

This project follows **PSR-12** coding standards, enforced by [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer). Run `composer lint` to check your code before submitting.

## Pull Request Guidelines

- Keep changes focused -- one feature or fix per PR.
- Write meaningful commit messages.
- Make sure all tests pass and linting is clean.
- Be respectful and constructive in discussions.

Thank you for helping improve HTTPCapture!
