# Contributing to Filament

Thank you for considering contributing to Filament! This guide will help you get started.

## Code of Conduct

Please read and follow our [Code of Conduct](CODE_OF_CONDUCT.md).

## Development Setup

1. Fork the repository and clone your fork:

```bash
git clone https://github.com/your-username/filament.git
cd filament
```

2. Install PHP dependencies:

```bash
composer install
```

3. Install JavaScript dependencies:

```bash
npm install
```

4. Build assets:

```bash
npm run build
```

## Coding Standards

Filament follows the coding standards defined in [AGENTS.md](./AGENTS.md). Key points:

- **Never use abbreviated variable names** — Use `$component`, `$record`, `$response` instead of `$comp`, `$rec`, `$res`
- **Use `make()` constructor** for fluent API
- **Use `static fn`** for closures that don't use `$this`
- **Use `app()`** instead of `new` for container resolution
- **Do not use `final` or `readonly` classes** — users need to extend Filament classes
- **CSS hook classes** must use `@apply` in CSS files, not Tailwind classes directly in Blade views
- **PHPDoc** only when providing type info beyond native PHP types

Run code style fixes before submitting:

```bash
composer cs
```

## Testing

Always update tests when making changes. For UI components, add browser tests using Pest Browser.

```bash
# Run all tests (SQLite + PHPStan)
composer test

# Run SQLite tests only
composer test:sqlite

# Run PHPStan static analysis
composer test:phpstan

# Run a single test file
vendor/bin/pest tests/src/Forms/Components/TextInputTest.php
```

Browser tests must call `assertNoAccessibilityIssues()` in both light and dark modes.

## Pull Request Process

1. Create a feature branch from `master`
2. Make your changes with tests
3. Run `composer cs` and `composer test`
4. Ensure all tests pass and code style is clean
5. Open a pull request against `master`
6. Update documentation in `docs/` or `packages/{package}/docs/` for user-facing features

## Security

If you discover a security vulnerability, please review our [Security Policy](SECURITY.md) and contact us privately.

## License

Filament is open-sourced software licensed under the [MIT license](LICENSE.md).
