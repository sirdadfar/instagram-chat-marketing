# CI/CD Production

بعد از هر Push به main، GitHub Actions ابتدا Composer و syntax فایل‌های PHP را بررسی می‌کند و سپس نسخه پروژه را با SSH روی سرور Deploy می‌کند.

## GitHub Secrets

در Settings → Secrets and variables → Actions این مقادیر را بسازید:

- SERVER_HOST: دامنه یا IP سرور
- SERVER_PORT: پورت SSH، معمولاً 22
- SERVER_USER: کاربر Deploy
- SERVER_PATH: مسیر پروژه، مثل /var/www/instagram-chat-marketing
- SERVER_SSH_KEY: کلید خصوصی SSH

رمز عبور SSH را در GitHub یا Repository ذخیره نکنید.

## .env

فایل .env فقط روی سرور نگهداری می‌شود و workflow آن را overwrite نمی‌کند.

## Queue

Redis لازم نیست. Queue روی database اجرا می‌شود. برای اجرای دائمی worker از Supervisor استفاده کنید.

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
