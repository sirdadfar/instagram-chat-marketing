# IG Automate — Instagram Automation Panel

<div align="center">

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8%2B-4479A1?logo=mysql&logoColor=white)
![Queue](https://img.shields.io/badge/Queue-database-blueviolet)
![CI/CD](https://img.shields.io/badge/CI/CD-GitHub%20Actions-2088FF?logo=githubactions&logoColor=white)
![Code Style](https://img.shields.io/badge/Style-Pint-F05340)
![License](https://img.shields.io/badge/License-MIT-green)
![Tests](https://github.com/sirdadfar/instagram-chat-marketing/actions/workflows/tests.yml/badge.svg)

**زبان:** [English](#english) · [فارسی](#فارسی)

</div>

## [![EN](https://img.shields.io/badge/lang-EN-5A67D8)](#english) <a id="english"></a>

A private, production-oriented **multi-tenant Instagram automation panel** built on top of the [Zernio](https://zernio.com) Instagram API. It turns comment/story/message events into reliable, rate-limited, fully logged automations — public replies, private replies, DMs, follow-gates, tagging and comment moderation — with a Persian RTL management UI.

> **Zero Redis. Zero Node build.** Queue is database-backed, assets are plain CSS/JS, and deployment is Apache + Supervisor + GitHub Actions.

---

### ✨ Feature Highlights

#### 🤖 Automation Engine
- **Triggers:** comment on post · story reply · direct message
- **Actions:** public comment reply · private reply · DM · hide comment · local contact tagging
- **Keyword matching:** `ANY` / `ALL` with `contains` / `exact` / `word` modes and keyword exclusions
- **Audience rules:** follower-only targeting with explicit handling for *unknown* followers (`send` / `skip` / `verify`)
- **Follow Gate:** sends a "I followed ✓" postback button, verifies follow status via Zernio, and re-enters the automation pipeline only when the user actually follows
- **Message delays:** independent delays for private replies and public replies
- **Safety controls:** global kill switch, business hours, rate-limit buckets, per-user cooldown & daily limits
- **Dry-run simulator:** test any automation against a sample event without side effects
- **Version history & run tracking:** every run, evaluation and action outcome is stored

#### 🛡️ Reliability & Observability
- **Signed webhooks:** HMAC-SHA256 signature verification of the raw body
- **At-least-once safe:** every webhook event is persisted with a unique event id → automatic deduplication
- **Database queue** with retries and backoff (`tries=3, backoff=5,30,120`) — no Redis required
- **Inbox** with conversations, messages, attachments, reactions, human-handoff and archiving
- **Local contact/conversation store** (`isFollower`, follower count, verified badge, profile data)
- **Audit log + automation logs + system health page**
- **Analytics dashboard** built on runs, logs and messages

#### 🏢 Multi-tenant Accounts
- **Open registration:** every user registers with username + password; the first user created automatically becomes admin
- **Hard data isolation:** page ownership (`user_id`) is the boundary for everything — automations, inbox, contacts, comments, content, message templates and media library
- **Per-user Zernio key:** each user enters their own API key in Settings; it is stored **encrypted in the database** and takes priority over the server's `.env` fallback
- **Admin supervision:** activate/deactivate users, reset passwords, promote to admin, delete accounts and reassign page ownership
- **Hardened auth:** uniform login errors (no username enumeration), throttled open registration, session login with "remember me"

#### 🎨 Panel (Persian RTL)
- Dashboard, automations builder (wizard + workflow builder), visual **media picker** (posts/stories as cards with video/reels/album filters, caption search and pagination)
- **Comments manager:** reply, private reply, delete, hide/unhide, like/unlike
- **Content studio:** media uploads, timeline, story insights
- Templates, media library, contacts, notifications, settings & account health, custom ice-breakers
- Installable **PWA** (dark theme, standalone display)
- Interactive **button builder** for DM automation (postback buttons)

#### 🏭 Production Ready
- Multi-account-ready database schema
- Apache deployment guide + Supervisor worker + cron scheduler
- Automated CI/CD via GitHub Actions (test → syntax check → SSH deploy on `main`)
- Security-hardened `.env` checklist and production documentation

---

### 🏗️ Architecture

```text
Instagram
   ↓
Zernio (Instagram OSINT / messaging API)
   ↓ HTTPS Webhook (HMAC-SHA256 signed)
Webhook Controller
   ↓ persist + dedupe by stable event id
webhook_events (MySQL)
   ↓ database queue
ProcessZernioWebhook (job)
   ↓
Automation Engine
   ├─ keyword / exclusions (ANY·ALL · contains·exact·word)
   ├─ audience / follower rules (incl. unknown → verify gate)
   ├─ priority · per-user cooldown · daily limits
   ├─ business hours · rate limit buckets
   └─ global kill switch
   ↓
Action Executor
   ├─ public comment reply      ├─ private reply
   ├─ DM (text · media · buttons) └─ hide comment / contact tagging
   ↓
Zernio API → Instagram
```

Panel HTTP layer: `PanelController` · `AuthController` (username/password session auth) · `PanelAuth` middleware · Blade views, plain CSS/JS.

---

### 🚀 Quick Start

**Requirements:** PHP 8.3+ · MySQL 8+ · Composer 2+ · Apache/Nginx with a public HTTPS URL (for webhooks).

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
```

Configure Zernio credentials in `.env` (`ZERNIO_API_KEY`, `ZERNIO_WEBHOOK_SECRET`, …), then:

```bash
# (Optional) bootstrap the admin user from .env values — idempotent, safe on every deploy
# PANEL_ADMIN_EMAIL / PANEL_ADMIN_USERNAME / PANEL_ADMIN_PASSWORD
php artisan panel:adopt-legacy

# Sync the connected Instagram account into the local store
php artisan zernio:account [ACCOUNT_ID] --username=your_username

# Register the production webhook subscription
php artisan zernio:webhook --url=https://your-domain.example/api/webhooks/zernio

# Start the queue worker (no Redis)
php artisan queue:work database --sleep=1 --tries=3 --backoff=5,30,120 --timeout=90
```

Then open the panel and **register** — the first user automatically becomes admin. Each user then adds their own Zernio API key in Settings (encrypted at rest, overrides the server `.env`).

Full production guide: [`docs/PRODUCTION.md`](docs/PRODUCTION.md) · installation notes: [`docs/INSTALL.md`](docs/INSTALL.md)

---

### ⌨️ Artisan Commands

| Command | Description |
|---|---|
| `panel:adopt-legacy` | Creates the admin user (if missing) and attaches ownerless pages/media/templates to it — idempotent, runs automatically after deploy |
| `panel:password-hash` | Legacy utility: bcrypt-hashes a password into `PANEL_ADMIN_PASSWORD_HASH` (pre-tenant installs) |
| `zernio:account [id] --username=` | Syncs Instagram account info from Zernio |
| `zernio:webhook --url=` | Creates the production webhook subscription; `--test=<id>` fires a test delivery |
| `zernio:sync-automation {id}` | Pushes one automation to Zernio's native Comment Automation |

---

### 🧩 Automation Trigger & Action Reference

| Trigger (`type`) | Fires on | Source |
|---|---|---|
| `comment` | comment.received | posts/stories with visual target selection |
| `story_reply` | message.received (story context) | active stories |
| `direct_message` | message.received / conversation.started | inbox conversations |

| Action (`type`) | Effect |
|---|---|
| `public_reply` | Public reply under the comment (with delay) |
| `private_reply` | Private reply to the comment → opens DM thread |
| `direct_message` | Rich DM: text, media, postback buttons (button builder), ice-breakers |
| `add_tag` | Local tagging of the contact |
| `hide_comment` | Hides the comment on Instagram |

Additional control effects in the pipeline: *Follow Gate* (`follow_gate:*` postback), **human handoff**, and contact tagging.

---

### 🧱 Message Template Variables

```text
{{username}}      {{full_name}}     {{comment}}       {{message}}
{{post_id}}       {{post_url}}      {{story_id}}      {{story_url}}
{{account_username}} {{date}}       {{time}}
```

---

### 🔔 Webhook Subscriptions (Zernio)

`comment.received` · `message.received` · `message.sent` · `message.delivered` · `message.read` · `message.failed` · `message.deleted` · `message.edited` · `reaction.received` · `conversation.started` · `conversation.control_changed`

> Zernio delivers webhooks **at-least-once**; this app deduplicates using the stable event id under a unique database constraint, so double deliveries never trigger an automation twice.

---

### ⚙️ Deployment

**Apache:** point the document root to `public/`, enable `rewrite` + `headers`.

**Supervisor worker** (`/etc/supervisor/conf.d/ig-automate-worker.conf`):

```ini
[program:ig-automate-worker]
command=/usr/bin/php /var/www/ig-automate/artisan queue:work database --sleep=1 --tries=3 --backoff=5,30,120 --timeout=90
directory=/var/www/ig-automate
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/ig-automate-worker.log
stopwaitsecs=3600
```

**Scheduler (optional):**

```cron
* * * * * cd /var/www/ig-automate && php artisan schedule:run >> /dev/null 2>&1
```

**CI/CD:** `.github/workflows/deploy.yml` runs `composer validate`, `composer install`, PHP syntax check (`php -l`) and then deploys via SSH to production on every push to `main`. See [`docs/CI_CD.md`](docs/CI_CD.md) for the required GitHub secrets (`SERVER_HOST`, `SERVER_PORT`, `SERVER_USER`, `SERVER_PATH`, `SERVER_SSH_KEY`) and the Supervisor naming convention (`ig-automate-worker*`).

---

### 🔐 Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] Long random `ZERNIO_WEBHOOK_SECRET` (HMAC verification depends on it)
- [ ] `ZERNIO_API_KEY` never exposed to browser JS
- [ ] Per-user Zernio keys stored encrypted (`zernio_api_key_enc`) — the server key exists only as fallback
- [ ] `.env` never committed
- [ ] MySQL restricted to localhost/private network
- [ ] HTTPS everywhere
- [ ] Regular DB backups

---

### 🗂️ Project Structure

```text
app/
  Console/Commands/       artisan CLI (panel & zernio)
  Enums/                  AutomationTrigger · AutomationAction
  Http/Controllers/       Panel · Auth · Webhook · Analytics · Content · Comments · Operations
  Http/Middleware/        PanelAuth · PanelAdmin
  Jobs/                   ProcessZernioWebhook
  Models/                 Automation* · Instagram* · Contact · Conversation · Message · ...
  Services/
    Automation/           AutomationEngine · ActionExecutor · TemplateRenderer · RateLimiter
                          KeywordMatcher · BusinessHours · WebhookEventAdapter · ZernioAutomationSync
    Zernio/               ZernioClient (API wrapper)
config/                   panel.php · zernio.php
database/migrations/      automation + operations tables
docs/                     full documentation (see below)
public/                   assets · manifest.webmanifest · sw.js
resources/views/          Blade templates (Persian RTL)
routes/                   web.php · api.php (webhooks)
```

---

### 📚 Documentation

| Doc | Purpose |
|---|---|
| [`docs/INSTALL.md`](docs/INSTALL.md) | Installation help |
| [`docs/PRODUCTION.md`](docs/PRODUCTION.md) | End-to-end production deployment |
| [`docs/CI_CD.md`](docs/CI_CD.md) | GitHub Actions workflow & secrets |
| [`docs/MESSAGING_MEDIA.md`](docs/MESSAGING_MEDIA.md) | DM & media capabilities |
| [`docs/ZERNIO_MEDIA.md`](docs/ZERNIO_MEDIA.md) | Media picker implementation notes |

---

### 🔍 Testing & QA

```bash
composer test        # PHPUnit (runs php artisan test)
vendor/bin/pint --dirty   # Code style for files you changed
```

The suite runs against an in-memory SQLite database and covers
authentication, webhook signature verification/deduplication, multi-tenant
isolation and the automation helpers.

---

<div align="center">

**License:** MIT — free for personal and commercial use · See [`LICENSE`](LICENSE) · Built with ❤️ for Instagram chat marketing

</div>

---

### 🤝 Contributing, Security & License

This project is open source under the **MIT License** — you may use it, modify it
and ship it commercially, provided the copyright notice and license text are
kept. See [`LICENSE`](LICENSE).

- **Contributing:** read [`CONTRIBUTING.md`](CONTRIBUTING.md) before opening a PR.
  We follow [Conventional Commits](https://www.conventionalcommits.org/) and PSR-12 (Pint).
- **Code of Conduct:** [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).
- **Security:** report vulnerabilities privately — see [`SECURITY.md`](SECURITY.md).
- **Changelog:** [`CHANGELOG.md`](CHANGELOG.md).

> **Third-party terms.** Running this panel requires a [Zernio](https://zernio.com)
> account/API key and an Instagram account connected through Meta's platform.
> You are responsible for complying with Zernio's and Meta/Instagram's terms of
> service and any applicable marketing/privacy regulations. The MIT license
> covers **this repository's code only**.

---

## [![FA](https://img.shields.io/badge/زبان-فارسی-38A89D)](#) <a id="فارسی"></a>

پنل حرفه‌ای و production-oriented **اتوماسیون اینستاگرام چندکاربره (Multi-tenant)** بر پایه API [زرنیو (Zernio)](https://zernio.com). این پنل رویدادهای کامنت/استوری/پیام اینستاگرام را به اتوماسیون‌های مطمئن، رِیت‌لیمیت‌شده و کاملاً لاگ‌شده تبدیل می‌کند — پاسخ عمومی، پاسخ خصوصی، دایرکت، Follow Gate، تگ‌گذاری مخاطب و مدیریت نظرات — با یک ادمین فارسی RTL موبایل‌محور.

> **بدون Redis و بدون Build Node.** صف روی دیتابیس، أسست‌ها CSS/JS ساده، و استقرار روی Apache + Supervisor + GitHub Actions.

---

### ✨ امکانات

#### 🤖 موتور اتوماسیون
- **محرک‌ها:** کامنت روی پست · پاسخ به استوری · پیام مستقیم
- **اکشن‌ها:** پاسخ عمومی کامنت · پاسخ خصوصی · دایرکت · مخفی‌سازی کامنت · تگ‌گذاری محلی مخاطب
- **تطبیق کلمات کلیدی:** حالت‌های `ANY` / `ALL` با `contains` / `exact` / `word` و کلمات مستثنی
- **قوانین مخاطب:** تمرکز روی فالوورها با رفتار مشخص برای «وضعیت نامشخص» (`ارسال` / `صرف‌نظر` / `بررسی`)
- **Follow Gate:** ارسال دکمه‌ی postback «فالو کردم ✓»، استعلام وضعیت فالو از Zernio و ادامه‌ی اتوماسیون فقط پس از تأیید واقعی فالو
- **تأخیر پیام:** تأخیر مستقل برای پاسخ خصوصی و پاسخ عمومی
- **کنترل‌های ایمنی:** کلید قطع سراسری (Kill Switch) · ساعات کاری · باکت‌های Rate Limit · Cooldown و سقف روزانه بر اساس کاربر
- **شبیه‌ساز Dry-Run:** آزمایش هر اتوماسیون روی رویداد نمونه بدون اثر جانبی
- **تاریخچه نسخه‌ها و رهگیری اجرا:** هر run، ارزیابی و نتیجه‌ی اکشن ذخیره می‌شود

#### 🛡️ پایداری و مانیتورینگ
- **وبهوک امضاشده:** اعتبارسنجی HMAC-SHA256 روی بدنه‌ی خام
- **مقاوم در برابر ارسال تکراری:** هر رویداد با شناسه‌ی پایدار و یونیک در `webhook_events` ذخیره و dedup می‌شود
- **صف دیتابیسی** با retry و backoff (`tries=3, backoff=5,30,120`) — بدون نیاز به Redis
- **Inbox** با گفتگوها، پیام‌ها، فایل‌ها، واکنش‌ها (Reactions)، تحویل به اپراتور انسانی و آرشیو گفتگو
- **ذخیره‌ی محلی مخاطب/گفتگو** (`isFollower`، تعداد فالوور، تیک آبی، داده‌های پروفایل)
- **Audit Log** + لاگ اجرای اتوماسیون‌ها + صفحه **سلامت سیستم**
- **داشبورد آنالیتیکس** بر پایه runها، لاگ‌ها و پیام‌ها

#### 🏢 حساب‌های چندکاربره
- **ثبت‌نام آزاد:** هر کاربر با نام کاربری و رمز ثبت‌نام می‌کند؛ نخستین کاربرِ ایجادشده به‌طور خودکار ادمین می‌شود
- **تفکیک سخت داده‌ها:** مالکیت پیج (`user_id`) مرز همه داده‌هاست — اتوماسیون‌ها، اینباکس، مخاطبین، نظرات، محتوا، قالب پیام و کتابخانه رسانه
- **کلید اختصاصی هر کاربر:** هر کاربر کلید Zernio خود را در «تنظیمات» وارد می‌کند؛ **رمزنگاری‌شده در دیتابیس** ذخیره می‌شود و اولویتش از `.env` سرور بیشتر است
- **نظارت ادمین:** فعال/غیرفعال کردن کاربر، ریست رمز، تبدیل به ادمین، حذف حساب و واگذاری مالکیت پیج‌ها
- **ورود سخت‌گیرانه:** خطای یکسان در لاگین (بدون امکان شناسایی نام‌های کاربری ثبت‌شده)، ثبت‌نام rate-limit شده، ورود session-based با «مرا به خاطر بسپار»

#### 🎨 پنل فارسی RTL
- داشبورد · سازنده اتوماسیون (فرم سریع + Workflow Builder) · **انتخاب بصری محتوا** (کارت تصویری پست‌ها/استوری‌ها با فیلتر ویدیو/ریلز/آلبوم و جستجو روی کپشن و pagination)
- **مدیریت نظرات:** ریپلای، پاسخ خصوصی، حذف، مخفی/آن‌مخفی، لایک/آن‌لایک
- **استودیو محتوا:** آپلود رسانه، Timeline، اینسایت‌های استوری
- قالب‌های پیام · کتابخانه رسانه · مخاطبین · اعلان‌ها · تنظیمات و **سلامت اکانت** · آیس‌بریکر اختصاصی
- **PWA نصب‌شدنی** با تم تیره (standalone)
- **Button Builder** تعاملی برای دایرکت (دکمه‌های postback)

#### 🏭 آماده Production
- اسکیمای دیتابیس برای **چند اکانت** (multi-account-ready)
- راهنمای دیپلوی Apache · کارگر Supervisor · کرون زمان‌بند
- **CI/CD خودکار** با GitHub Actions (تست → syntax check → دیپلوی SSH روی `main`)
- چک‌لیست امنیتی `.env` و مستندات نسخه production

---

### 🏗️ معماری

```text
Instagram
   ↓
Zernio (API پیام و تحلیل اینستاگرام)
   ↓ HTTPS Webhook (امضای HMAC-SHA256)
Webhook Controller
   ↓ ذخیره + dedup با شناسه رویداد پایدار
webhook_events (MySQL)
   ↓ database queue
ProcessZernioWebhook (job)
   ↓
Automation Engine
   ├─ کلمه کلیدی و مستثنیات (ANY·ALL · contains·exact·word)
   ├─ قوانین مخاطب و فالوور (حالت «بررسی» برای نامشخص)
   ├─ اولویت · Cooldown کاربر · سقف روزانه
   ├─ ساعات کاری · باکت Rate Limit
   └─ کلید قطع سراسری
   ↓
Action Executor
   ├─ پاسخ عمومی کامنت          ├─ پاسخ خصوصی → دایرکت
   ├─ دایرکت (متن · رسانه · دکمه) └─ مخفی‌سازی کامنت / تگ مخاطب
   ↓
Zernio API → Instagram
```

لایه HTTP پنل: `PanelController` · `AuthController` (ورود با نام کاربری/رمز و session) · میدل‌ور `PanelAuth` · تمپلیت‌های Blade با CSS/JS ساده.

---

### 🚀 راه‌اندازی سریع

**پیش‌نیازها:** PHP 8.3+ · MySQL 8+ · Composer 2+ · وب‌سرور با دامنه HTTPS عمومی (برای وبهوک).

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
```

مقادیر مربوط به Zernio را در `.env` تنظیم کنید (`ZERNIO_API_KEY`, `ZERNIO_WEBHOOK_SECRET`, …) و سپس:

```bash
# (اختیاری) ساخت کاربر ادمین از مقادیر .env — idempotent و در هر deploy امن
# PANEL_ADMIN_EMAIL / PANEL_ADMIN_USERNAME / PANEL_ADMIN_PASSWORD
php artisan panel:adopt-legacy

# همگام‌سازی اکانت اینستاگرام متصل از Zernio
php artisan zernio:account [ACCOUNT_ID] --username=your_username

# ثبت اشتراک وبهوک Production
php artisan zernio:webhook --url=https://your-domain.example/api/webhooks/zernio

# اجرای Queue Worker (بدون Redis)
php artisan queue:work database --sleep=1 --tries=3 --backoff=5,30,120 --timeout=90
```

سپس پنل را باز کنید و **ثبت‌نام** کنید — نخستین کاربر به‌طور خودکار ادمین می‌شود. سپس هر کاربر کلید اختصاصی Zernio خود را در «تنظیمات» وارد می‌کند (رمزنگاری‌شده ذخیره می‌شود و بر `.env` سرور اولویت دارد).

راهنمای کامل Production: [`docs/PRODUCTION.md`](docs/PRODUCTION.md) · نصب: [`docs/INSTALL.md`](docs/INSTALL.md)

---

### ⌨️ دستورات Artisan

| دستور | توضیح |
|---|---|
| `panel:adopt-legacy` | ساخت کاربر ادمین (در صورت نبود) و واگذاری پیج‌ها/رسانه‌ها/قالب‌های بی‌صاحب به ادمین — idempotent و پس از هر deploy خودکار |
| `panel:password-hash` | ابزار قدیمی: هش bcrypt برای `PANEL_ADMIN_PASSWORD_HASH` (نصب‌های قبل از چندکاربره شدن) |
| `zernio:account [id] --username=` | همگام‌سازی اطلاعات اکانت اینستاگرام از Zernio |
| `zernio:webhook --url=` | ساخت اشتراک وبهوک production؛ `--test=<id>` برای ارسال تست |
| `zernio:sync-automation {id}` | سینک یک اتوماسیون با Comment Automation بومی زرنیو |

---

### 🧩 مرجع محرک‌ها و اکشن‌ها

| محرک (`type`) | رویداد | منبع |
|---|---|---|
| `comment` | comment.received | پست/استوری با انتخاب بصری محتوا |
| `story_reply` | message.received (بستر استوری) | استوری‌های فعال |
| `direct_message` | message.received / conversation.started | مکالمات Inbox |

| اکشن (`type`) | اثر |
|---|---|
| `public_reply` | ریپلای عمومی زیر کامنت (با تأخیر) |
| `private_reply` | پاسخ خصوصی به کامنت → شروع مکالمه دایرکت |
| `direct_message` | دایرکت غنی: متن، رسانه، دکمه‌های postback (Button Builder)، آیس‌بریکر |
| `add_tag` | ثبت تگ محلی روی مخاطب |
| `hide_comment` | مخفی کردن کامنت روی اینستاگرام |

جریان‌های کنترلی دیگر: **Follow Gate** (postback به شکل `follow_gate:*`)، **تحویل به کاربر انسانی** (Human Handoff) و تگ‌گذاری مخاطب.

---

### 🧱 متغیرهای تمپلیت پیام

```text
{{username}}      {{full_name}}     {{comment}}       {{message}}
{{post_id}}       {{post_url}}      {{story_id}}      {{story_url}}
{{account_username}} {{date}}       {{time}}
```

---

### 🔔 رویدادهای اشتراک وبهوک (Zernio)

`comment.received` · `message.received` · `message.sent` · `message.delivered` · `message.read` · `message.failed` · `message.deleted` · `message.edited` · `reaction.received` · `conversation.started` · `conversation.control_changed`

> رویدادهای وبهوک Zernio **at-least-once** هستند؛ این پنل با شناسه‌ی پایدار رویداد و محدودیت یونیک در دیتابیس، پردازش تکراری را کاملاً حذف می‌کند — هیچ اتوماسیونی دو بار اجرا نمی‌شود.

---

### ⚙️ استقرار

**Apache:** Document Root باید روی `public/` باشد و ماژول‌های `rewrite` + `headers` فعال شوند.

**کارگر Supervisor** (`/etc/supervisor/conf.d/ig-automate-worker.conf`):

```ini
[program:ig-automate-worker]
command=/usr/bin/php /var/www/ig-automate/artisan queue:work database --sleep=1 --tries=3 --backoff=5,30,120 --timeout=90
directory=/var/www/ig-automate
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/ig-automate-worker.log
stopwaitsecs=3600
```

**زمان‌بند (اختیاری):**

```cron
* * * * * cd /var/www/ig-automate && php artisan schedule:run >> /dev/null 2>&1
```

**CI/CD:** فایل `.github/workflows/deploy.yml` پس از هر push به `main`، مراحل `composer validate` → `composer install` → syntax check (`php -l`) → دیپلوی SSH را اجرا می‌کند. Secrets لازم: `SERVER_HOST`, `SERVER_PORT`, `SERVER_USER`, `SERVER_PATH`, `SERVER_SSH_KEY`. نکته: نام برنامه Supervisor باید با `ig-automate-worker` شروع شود تا workflow بتواند آن را restart کند. جزئیات در [`docs/CI_CD.md`](docs/CI_CD.md).

---

### 🔐 چک‌لیست امنیتی

- [ ] `APP_DEBUG=false` در production
- [ ] `ZERNIO_WEBHOOK_SECRET` تصادفی و طولانی (HMAC به آن وابسته است)
- [ ] هرگز `ZERNIO_API_KEY` را به جاوااسکریپت مرورگر expose نکنید
- [ ] کلیدهای Zernio هر کاربر رمزنگاری‌شده (`zernio_api_key_enc`) ذخیره می‌شوند — کلید سرور فقط نقش fallback دارد
- [ ] هرگز `.env` را commit نکنید
- [ ] MySQL محدود به localhost یا شبکه داخلی
- [ ] HTTPS در تمام پنل و وبهوک
- [ ] بکاپ منظم دیتابیس

---

### 🗂️ ساختار پروژه

```text
app/
  Console/Commands/       دستورات artisan (پنل و زرنیو)
  Enums/                  AutomationTrigger · AutomationAction
  Http/Controllers/       Panel · Auth · Webhook · Analytics · Content · Comments · Operations
  Http/Middleware/        PanelAuth · PanelAdmin
  Jobs/                   ProcessZernioWebhook
  Models/                 Automation* · Instagram* · Contact · Conversation · Message · ...
  Services/
    Automation/           AutomationEngine · ActionExecutor · TemplateRenderer · RateLimiter
                          KeywordMatcher · BusinessHours · WebhookEventAdapter · ZernioAutomationSync
    Zernio/               ZernioClient (Wrapper API)
config/                   panel.php · zernio.php
database/migrations/      جدول‌های اتوماسیون و عملیات
docs/                     مستندات کامل
public/                   assets · manifest.webmanifest · sw.js
resources/views/          تمپلیت‌های Blade (فارسی RTL)
routes/                   web.php · api.php (وبهوک)
```

---

### 📚 مستندات

| سند | موضوع |
|---|---|
| [`docs/INSTALL.md`](docs/INSTALL.md) | راهنمای نصب |
| [`docs/PRODUCTION.md`](docs/PRODUCTION.md) | استقرار گام‌به‌گام production |
| [`docs/CI_CD.md`](docs/CI_CD.md) | Workflow و secrets گیت‌هاب |
| [`docs/MESSAGING_MEDIA.md`](docs/MESSAGING_MEDIA.md) | قابلیت‌های دایرکت و رسانه |
| [`docs/ZERNIO_MEDIA.md`](docs/ZERNIO_MEDIA.md) | جزئیات پیاده‌سازی انتخاب بصری محتوا |

---

### 🔍 تست و پوشش کد

```bash
composer test        # PHPUnit (php artisan test)
vendor/bin/pint      # یکدست‌سازی استایل کد
```

---

<div align="center">

**لایسنس:** MIT — رایگان برای استفاده شخصی و تجاری · جزئیات در [`LICENSE`](LICENSE) · ساخته‌شده با ❤️ برای چت مارکتینگ اینستاگرام

</div>

---

### 🤝 مشارکت، امنیت و لایسنس

این پروژه تحت **مجوز MIT** اوپن‌سورس است — می‌توانی از آن استفاده کنی، تغییرش دهی و
حتی به‌صورت تجاری منتشرش کنی، به شرط حفظ متن لایسنس و اعلان کپی‌رایت. جزئیات: [`LICENSE`](LICENSE).

- **مشارکت:** قبل از ارسال PR، [`CONTRIBUTING.md`](CONTRIBUTING.md) را بخوان.
  ما [Conventional Commits](https://www.conventionalcommits.org/) و PSR-12 (Pint) را رعایت می‌کنیم.
- **آیین‌نامه رفتار:** [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).
- **امنیت:** آسیب‌پذیری‌ها را به‌صورت خصوصی گزارش کن — [`SECURITY.md`](SECURITY.md).
- **تغییرات:** [`CHANGELOG.md`](CHANGELOG.md).

> **شرایط سرویس‌های ثالث.** اجرای این پنل به حساب/کلید API [زرنیو](https://zernio.com) و
> یک حساب اینستاگرام متصل از طریق پلتفرم متا نیاز دارد. رعایت شرایط سرویس زرنیو و
> متا/اینستاگرام و قوانین بازاریابی و حریم خصوصی بر عهده‌ی شماست. مجوز MIT فقط
> شامل **کد همین مخزن** می‌شود.
