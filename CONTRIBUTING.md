# Contributing to NIZAM

Thank you for your interest in contributing to NIZAM — Open Communications Control Platform.

## Code of Conduct

By participating in this project, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

---

## How to Contribute

### Reporting Bugs

Before submitting a bug report:
1. Check the [existing issues](https://github.com/md-riaz/NIZAM/issues) to avoid duplicates.
2. Collect relevant information: PHP version, Laravel version, FreeSWITCH version, OS, steps to reproduce, expected vs actual behavior.
3. Open an issue using the **Bug Report** template.

### Suggesting Enhancements

Feature requests should:
1. Clearly describe the problem being solved, not just the solution.
2. Fit within the [v1.0 scope](docs/v1-scope.md) or be clearly labeled as a roadmap item.
3. Be opened as an issue for discussion before a pull request is submitted.

### Pull Requests

1. **Fork** the repository and create your branch from `main`.
2. Branch naming: `feature/<short-description>`, `fix/<issue-number>`, `docs/<topic>`.
3. Follow the coding standards described below.
4. Add or update tests for any changed behavior.
5. Ensure all tests pass: `php artisan test`.
6. Ensure code is linted: `vendor/bin/pint`.
7. Update documentation for any changed or new behavior.
8. Submit the pull request against the `main` branch.

---

## Development Setup

```bash
git clone https://github.com/md-riaz/NIZAM.git
cd NIZAM
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
```

For Docker-based development see [docs/environment-bootstrap.md](docs/environment-bootstrap.md).

---

## Coding Standards

NIZAM follows the **PSR-12** coding standard enforced by [Laravel Pint](https://laravel.com/docs/pint).

Run the linter before submitting:

```bash
vendor/bin/pint
```

### Architecture Guidelines

- **API-first**: Every operation goes through the REST API. See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
- **Multi-tenant by default**: Every resource is scoped to a tenant.
- **No business logic in controllers**: Controllers validate and delegate; services own logic.
- **Dialplan as compiled artifact**: Never hand-author FreeSWITCH XML. Use the `DialplanCompiler`.
- **Events as immutable records**: Append-only call event logs. No retroactive edits.

---

## Testing

NIZAM uses PHPUnit with an in-memory SQLite database for speed.

```bash
# Run all tests
php artisan test

# Run a specific test file
php artisan test tests/Feature/Api/ExtensionApiTest.php

# Run a specific test
php artisan test --filter test_extension_can_be_created
```

All pull requests must pass the full test suite. Do not remove or weaken existing tests.

---

## Module Development

NIZAM supports pluggable feature modules. To build a new module, follow the
[Module Development Guide](docs/module-development.md).

Modules must:
- Implement the `NizamModule` contract.
- Be governed by nwidart activation (no manual activation bypasses).
- Register hooks/listeners only when enabled.
- Not inject raw XML into the dialplan.

---

## Versioning

NIZAM follows [Semantic Versioning 2.0.0](https://semver.org/). See
[docs/versioning-and-releases.md](docs/versioning-and-releases.md) for the full policy.

Breaking changes to the public API or event schema require a MAJOR version bump and
**must not** be introduced after v1.0.0 without a deprecation period.

---

## Security

If you discover a security vulnerability, please **do not** open a public issue.
Instead, send details privately to the maintainers. We will acknowledge receipt within
48 hours and work to address the issue promptly.

---

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
