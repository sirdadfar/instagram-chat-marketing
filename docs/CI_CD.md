# CI/CD

این پروژه با GitHub Actions بعد از هر Push به `main` ابتدا تست و syntax check می‌شود و سپس به سرور Production Deploy می‌شود.

## Secrets موردنیاز

در GitHub از مسیر **Settings → Secrets and variables → Actions** این Secrets را بسازید:

- `SERVER_HOST` — دامنه یا IP سرور
- `SERVER_PORT` — معمولاً `22`
- `SERVER_USER` — کاربر SSH
- `SERVER_PATH` — مسیر پروژه روی سرور، مثل `/var/www/instagram-chat-marketing`
- `SERVER_SSH_KEY` — کلید خصوصی SSH مربوط به کاربر Deploy

رمز عبور SSH را داخل GitHub، فایل workflow یا `.env` قرار ندهید.

## آماده‌سازی سرور

کاربر Deploy باید روی مسیر پروژه دسترسی نوشتن داشته باشد و PHP 8.3، Composer، MySQL و Apache نصب باشند.

در اولین نصب، `.env` را دستی روی سرور بسازید و مقادیر Production را داخل آن قرار دهید. CI/CD فایل `.env` را overwrite نمی‌کند.

برای Queue بدون Redis می‌توانید Supervisor را با worker زیر تنظیم کنید:

```ini
[program:ig-automate-worker]
command=php /var/www/instagram-chat-marketing/artisan queue:work database --sleep=1 --tries=3 --backoff=5,30,120
directory=/var/www/instagram-chat-marketing
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/ig-automate-worker.log
```

نام برنامه Supervisor باید با `ig-automate-worker` شروع شود تا workflow بتواند آن را restart کند.
