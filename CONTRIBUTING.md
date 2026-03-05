# Contributing to WP AI Agent

Thank you for your interest in contributing to WP AI Agent!

## Getting Started

1. Clone the repository
2. Install dependencies:

```bash
composer install
```

3. Verify your setup:

```bash
composer test      # Run tests
composer phpstan   # Static analysis (level 8)
composer phpcs     # Code style check
```

## Development Workflow

1. Create a feature branch from `trunk`
2. Make your changes
3. Ensure all checks pass before submitting:

```bash
composer test      # All tests must pass
composer phpstan   # No errors allowed
composer phpcs     # No errors allowed
```

4. Submit a pull request against `trunk`

## Code Standards

- **PHP 8.1+** with `declare(strict_types=1)` in every file
- **PSR-12** with tabs for indentation
- **PHPStan level 8** — no baseline, all errors must be resolved
- Variables and properties use `$snake_case`
- Public/protected methods require PHPDoc with `@since` tags

## Architecture

The project follows a two-layer architecture:

- **Core** (`src/Core/`) — Platform-agnostic business logic. Must not reference WordPress APIs.
- **Integration** (`src/Integration/`) — WordPress and WP-CLI implementations.

Dependencies flow inward: Integration depends on Core, never the reverse. Core classes depend on interfaces defined in `Core/Contracts/`.

## Testing

- Tests live in `tests/Unit/` mirroring the `src/` structure
- Test files follow `{ClassName}Test.php` naming
- Use Arrange-Act-Assert pattern
- Mock interfaces, not concrete classes

## Reporting Issues

Please open an issue on GitHub with:

- A clear description of the problem or feature request
- Steps to reproduce (for bugs)
- Expected vs actual behavior
- PHP version and WordPress version

## License

By contributing, you agree that your contributions will be licensed under the GPL-2.0-or-later license.
