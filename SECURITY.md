# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

Only the latest `main` is actively maintained.

## Reporting a Vulnerability

Please **do not** open a public issue for security problems.

Use either channel:

1. **GitHub private vulnerability reporting** — go to the repository's
   **Security → Advisories → Report a vulnerability**.
2. **Email** — send the details to the maintainer listed in `composer.json`
   / the repository profile.

Please include:

- A description of the issue and its impact.
- Steps to reproduce (PoC, request/response payloads, affected route).
- Affected version, commit hash or branch.
- Any suggested fix or mitigation.

We aim to acknowledge reports within **72 hours** and to ship a fix or
mitigation as soon as reasonably possible.

## Hardening Checklist for Deployments

- `APP_DEBUG=false` and `APP_ENV=production`.
- `APP_KEY` generated with `php artisan key:generate` and kept secret
  (it encrypts per-user API keys and webhook secrets at rest).
- A long, random `ZERNIO_WEBHOOK_SECRET`; every inbound webhook is verified
  with HMAC-SHA256 over the **raw** request body.
- Never expose `ZERNIO_API_KEY` to browser JavaScript.
- Per-user service keys are stored encrypted (`zernio_api_key_enc`) and there
  is **no** server-wide fallback — each page is billed/bound to the owner's key.
- `.env` is never committed.
- MySQL is reachable only on localhost or a private network.
- HTTPS everywhere; the panel and webhook endpoints must not be reachable
  over plain HTTP.
- Regular database backups and a queue worker under Supervisor.

## Out of Scope

- Vulnerabilities in third-party APIs (Zernio, Meta/Instagram).
- Denial of service caused by misconfiguration or lack of rate limiting at the
  network/proxy layer.
- Issues that require a compromised admin account or leaked credentials.
