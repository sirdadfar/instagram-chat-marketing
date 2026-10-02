# IG Automate — Professional Instagram Automation Panel

A private, production-oriented Laravel panel for Instagram automation through Zernio.

## Highlights
- Laravel 12 / PHP 8.3+
- MySQL
- Database Queue — **no Redis required**
- Zernio Instagram API + signed webhooks
- Comment Reply automation
- Story Reply automation
- Direct Message automation
- Public reply / private reply / DM / hide comment / local tags
- Keyword matching + exclusions
- ANY / ALL + contains / exact / word modes
- Follower audience rules and unknown handling
- Priority and per-user cooldown / daily limits
- Global kill switch
- Business hours
- Rate limiting
- Human handoff
- Local conversation/contact store
- Inbox
- Automation execution logs
- Webhook event store + deduplication
- Automation run tracking
- Version history
- Dry-run simulator
- Native Zernio automation sync mode
- Persian RTL custom UI
- Apache-ready
- Supervisor-ready
- Multi-account-ready database architecture

## Installation
See `docs/PRODUCTION.md`.

Quick start:
```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan panel:password-hash
php artisan zernio:account
php artisan zernio:webhook --url=https://YOUR-DOMAIN/api/webhooks/zernio
php artisan queue:work database --sleep=1 --tries=3 --backoff=5,30,120
```

## Template variables
```text
{{username}}
{{full_name}}
{{comment}}
{{message}}
{{post_id}}
{{post_url}}
{{story_id}}
{{story_url}}
{{account_username}}
{{date}}
{{time}}
```

## Important Zernio behavior
Instagram supports comment-to-DM, story-reply automation, inbox DMs, private replies and rich DM elements. Private replies have Meta/Zernio limits including one private reply per comment and a 7-day window. Zernio webhook deliveries are at-least-once; this application therefore deduplicates by the stable event id.

## انتخاب بصری محتوای اینستاگرام

در صفحه ساخت اتوماسیون، برای محرک‌های «کامنت روی پست» و «پاسخ به استوری»، کاربر نباید شناسه محتوا را وارد کند. پنل از Zernio رسانه‌ها را دریافت می‌کند و آن‌ها را به‌صورت کارت تصویری نمایش می‌دهد.

- امکان انتخاب «همه پست‌ها» یا «همه استوری‌ها» وجود دارد.
- برای پست‌ها فیلتر تصویر، ویدیو، ریلز و آلبوم در نظر گرفته شده است.
- جستجو روی کپشن رسانه انجام می‌شود.
- شناسه واقعی مدیا فقط در `target_id` فیلد مخفی فرم نگهداری می‌شود.
- برای رسانه‌های بیشتر، pagination از Zernio استفاده می‌شود.

این بخش بر پایه endpointهای فعلی Zernio برای فهرست پست‌های حساب و استوری‌های فعال پیاده‌سازی شده است.
