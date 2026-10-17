<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InstagramAccount;
use App\Models\MediaLibrary;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Support\Credentials;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
/**
 * One-time migration helper for existing installations:
 *   - ensures at least one admin user exists (from .env data or stored settings),
 *   - hands all owner-less pages / media / templates to the admin.
 * Safe to run multiple times (idempotent); runs automatically after deploy.
 */
class AdoptLegacyData extends Command
{
    protected $signature = 'panel:adopt-legacy {--email= : Admin email override (default from PANEL_ADMIN_EMAIL)}';

    protected $description = 'Create the admin user (if missing) and attach ownerless Instagram pages, media and templates to it';

    public function handle(): int
    {
        $admin = $this->ensureAdmin();

        $pages = InstagramAccount::query()->whereNull('user_id')->update(['user_id' => $admin->id]);
        $media = MediaLibrary::query()->whereNull('user_id')->update(['user_id' => $admin->id]);
        $templates = MessageTemplate::query()->whereNull('user_id')->update(['user_id' => $admin->id]);

        $this->info("Admin user ready: {$admin->email}");
        $this->line("Attached {$pages} page(s), {$media} media item(s), {$templates} template(s) to the admin.");

        return Command::SUCCESS;
    }

    private function ensureAdmin(): User
    {
        $admin = User::query()->where('is_admin', true)->orderBy('id')->first();

        if ($admin !== null) {
            return $admin;
        }

        $email = strtolower(trim((string) ($this->option('email') ?: env('PANEL_ADMIN_EMAIL', 'admin@localhost'))));

        $admin = User::query()->where('email', $email)->first();

        if ($admin === null) {
            // Prefer the configured/stored panel password, otherwise generate one and show it.
            $passwordPlain = $this->resolveInitialPassword();

            $admin = User::create([
                'name' => (string) env('PANEL_ADMIN_LABEL', 'مدیر سامانه'),
                'email' => $email,
                'password' => $passwordPlain,
                'is_admin' => true,
                'status' => 'active',
                'zernio_api_key_enc' => null,
            ]);

            if ($this->generatedPassword !== null) {
                $this->warn('---------------------------------------------');
                $this->warn('رمز عبور ادمین ساخته‌شده (فقط همین روز نمایش داده می‌شود):');
                $this->warn($this->generatedPassword);
                $this->warn('بعد از ورود، از «تنظیمات» پنل است را تغییر بدهید. یا PANEL_ADMIN_PASSWORD را در .env ست کن.');
                $this->warn('---------------------------------------------');
            }
        }

        $admin->update(['is_admin' => true, 'status' => 'active']);

        return $admin;
    }

    private ?string $generatedPassword = null;

    private function resolveInitialPassword(): string
    {
        // 1) Explicit plain password in .env
        $plain = env('PANEL_ADMIN_PASSWORD');
        if (is_string($plain) && $plain !== '') {
            return $plain;
        }

        // 2) Previously stored (encrypted) panel password from Settings UI
        $stored = Credentials::panelPasswordHash();
        if ($stored !== '') {
            // It is a bcrypt-usable value; store the hash as the user password.
            return $stored;
        }

        // 3) Nothing configured → generate and print once
        $this->generatedPassword = Str::password(14);
        return $this->generatedPassword;
    }
}
