# IG Automate — Production Deployment

## Requirements
- PHP 8.3+
- MySQL 8+
- Apache 2.4 + mod_rewrite
- Composer 2+
- HTTPS public URL for Zernio webhooks
- Redis is NOT required

## 1. Install
```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
```

Generate the panel password hash:
```bash
php artisan panel:password-hash
```
Put the returned hash in `PANEL_ADMIN_PASSWORD_HASH`.

## 2. Sync the connected Instagram account
With `ZERNIO_API_KEY` configured:
```bash
php artisan zernio:account
```
Or:
```bash
php artisan zernio:account YOUR_ZERNIO_ACCOUNT_ID --username=your_username
```

## 3. Configure Zernio webhook
```bash
php artisan zernio:webhook --url=https://your-domain.example/api/webhooks/zernio
```
Zernio signs the raw JSON body with HMAC-SHA256. The same secret must be in `ZERNIO_WEBHOOK_SECRET`.

## 4. Apache
Point the virtual host document root to:
```text
/path/to/instagram-automation/public
```
Enable:
```bash
sudo a2enmod rewrite headers
sudo systemctl reload apache2
```
Never point Apache to the Laravel project root.

## 5. Queue worker — no Redis
Run:
```bash
php artisan queue:work database --sleep=1 --tries=3 --backoff=5,30,120 --timeout=90
```

Recommended Supervisor program:
```ini
[program:ig-automate-worker]
process_name=%(program_name)s_%(process_num)02d
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

## 6. Scheduler
This build does not require Redis. If you add scheduled maintenance later, run:
```cron
* * * * * cd /var/www/ig-automate && php artisan schedule:run >> /dev/null 2>&1
```

## 7. Security
- Never expose `ZERNIO_API_KEY` to browser JavaScript.
- Never commit `.env`.
- Use HTTPS.
- Use a long random `ZERNIO_WEBHOOK_SECRET`.
- Keep `APP_DEBUG=false` in production.
- Restrict MySQL to localhost/private network.
- Back up the database.

## 8. Architecture
```text
Instagram
   ↓
Zernio
   ↓ HTTPS Webhook + HMAC
Webhook Controller
   ↓ persist + dedupe
webhook_events
   ↓ database queue
ProcessZernioWebhook
   ↓
Automation Engine
   ├─ keyword / exclusions
   ├─ audience / follower rules
   ├─ priority
   ├─ cooldown + rate limit
   ├─ business hours
   └─ global safety switch
   ↓
Action Executor
   ├─ public comment reply
   ├─ private reply
   ├─ DM
   ├─ hide comment
   └─ local contact tagging
   ↓
Zernio API
```

Zernio webhooks are at-least-once, so the app stores the stable event id under a unique database constraint before queueing it.
