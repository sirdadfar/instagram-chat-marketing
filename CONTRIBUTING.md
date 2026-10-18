# Contributing to IG Automate

Thanks for taking the time to contribute! This document describes how to set
up the project locally, the coding standards we follow and how to get a change
merged.

## Requirements

- PHP 8.3+ with the `pdo_mysql` (production) and `pdo_sqlite` + `sqlite3`
  (tests) extensions
- Composer 2+
- MySQL 8+ for running the app locally (SQLite in-memory is used for tests)
- No Node/Redis required — assets are plain CSS/JS and the queue is database-backed

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

The panel is served from `public/`; point your web server (or `php artisan serve`)
at it. The first user you register becomes the admin.

## Running the checks

```bash
composer test             # PHPUnit (runs php artisan test)
vendor/bin/pint --dirty   # Format only the files you changed
```

Please make sure the test suite passes before opening a pull request.

## Coding standards

- Follow **PSR-12** where practical. The front end and older modules predate the
  formatter, so run **Laravel Pint** only on the files you touched:

  ```bash
  vendor/bin/pint --dirty
  ```

  New code should be Pint-clean; a full `vendor/bin/pint` run is welcome but is
  not required for a change to be merged.
- Prefer small, focused classes and constructor injection.
- The UI is **Persian / RTL**: keep user-facing strings in Persian and format
  numbers with the existing `toFa` helper.
- The front end is Blade + `public/js/panel.js` + `public/css/panel.css`
  (no build step). If you touch the message composer, verify both the
  *create* and *edit* automation screens.
- Never commit `.env` files, API keys or webhook secrets.

## Commit messages

We use [Conventional Commits](https://www.conventionalcommits.org/):

```
feat(automations): add per-user daily limit
fix(webhook): verify signature against the raw body
docs(readme): document the queue worker
```

Keep the summary short and in the imperative mood.

## Pull requests

1. Fork the repository and create a feature branch.
2. Keep the change focused; update docs/tests where relevant.
3. Ensure `composer test` and `vendor/bin/pint --test` pass.
4. Describe **what** changed and **why** in the PR description.

## Security

Please report security issues privately — see [`SECURITY.md`](SECURITY.md).
