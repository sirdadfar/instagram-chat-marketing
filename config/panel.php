<?php

return [
    // Legacy pre-tenant panel password hash (kept for backwards compatibility).
    'auth_password_hash' => env('PANEL_ADMIN_PASSWORD_HASH', ''),
    'admin_label' => env('PANEL_ADMIN_LABEL', 'مدیر پنل'),

    /*
     * One-time admin bootstrap values, consumed only by the `panel:adopt-legacy`
     * command. Read from config (not env) so they keep working when the deploy
     * pipeline runs `php artisan config:cache`.
     */
    'admin_name' => env('PANEL_ADMIN_LABEL', 'مدیر سامانه'),
    'admin_email' => env('PANEL_ADMIN_EMAIL'),
    'admin_username' => env('PANEL_ADMIN_USERNAME'),
    'admin_password' => env('PANEL_ADMIN_PASSWORD'),
];
