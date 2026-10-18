# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Open-source packaging: MIT license, contributing guide, code of conduct,
  security policy, CI workflow and a PHPUnit test suite.

### Fixed
- Admin bootstrap and Zernio helper commands now read their values from
  `config()` instead of `env()`, so they keep working after `config:cache`.
- `Settings` no longer re-syncs other tenants' automations to the service.
- Added the missing `storage/` scaffolding required by a fresh clone.
- Committed `composer.lock` for reproducible installs.

## [1.0.0]

### Added
- Multi-tenant Instagram automation panel built on Laravel 12 and the
  Zernio API.
- Automation engine with comment / story-reply / direct-message triggers and
  public reply, private reply, DM (text, media, buttons), hide-comment and
  contact-tagging actions.
- Keyword matching (`ANY` / `ALL`, `contains` / `exact` / `word`, exclusions),
  audience/follower rules, follow-gate with postback verification, message
  delays and a dry-run simulator.
- Safety controls: global kill switch, business hours, rate-limit buckets,
  per-user cooldown and daily limits.
- Signed (HMAC-SHA256) webhooks with at-least-once deduplication and a
  database-backed queue.
- Inbox with conversations, messages, attachments, reactions, human handoff
  and archiving; comments manager; content studio; analytics; contacts;
  templates; media library and system health.
- Open registration with username + password; the first user becomes admin.
  Per-user Zernio keys stored encrypted at rest.
- Installable PWA with a Persian RTL interface.

[Unreleased]: https://github.com/sirdadfar/instagram-chat-marketing/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/sirdadfar/instagram-chat-marketing/releases/tag/v1.0.0
