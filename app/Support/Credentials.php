<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Resolves runtime credentials with this priority:
 *   1. Value saved from the Settings screen (stored encrypted in the DB).
 *   2. Fallback from config (originally sourced from .env, cache-safe).
 *
 * Anything saved here beats the environment, so every user can configure
 * the panel from the UI without touching .env.
 */
final class Credentials
{
    /** Encrypted-at-rest marker prefix. */
    private const ENC_PREFIX = 'enc:';

    public static function zernioApiKey(): ?string
    {
        return self::storedOrConfig('zernio_api_key', 'zernio.api_key');
    }

    public static function zernioWebhookSecret(): ?string
    {
        return self::storedOrConfig('zernio_webhook_secret', 'zernio.webhook_secret');
    }

    /**
     * Returns a bcrypt-usable value for Hash::check().
     * Plain text saved from Settings is hashed transparently.
     */
    public static function panelPasswordHash(): string
    {
        $stored = self::stored('panel_password');

        if ($stored === null) {
            return (string) config('panel.auth_password_hash', '');
        }

        return self::looksLikeBcrypt($stored) ? $stored : Hash::make($stored);
    }

    /**
     * Stores a credential encrypted at rest. Empty input is a no-op so
     * submitting a settings form never wipes an existing value, and the
     * .env fallback stays active until the user actually types something.
     */
    public static function remember(string $key, mixed $plain): void
    {
        $value = trim((string) $plain);

        if ($value === '') {
            return;
        }

        AppSetting::setValue($key, self::ENC_PREFIX . Crypt::encryptString($value));
    }

    /**
     * Where the effective value comes from:
     *   'custom' → set from Settings UI, 'env' → from environment, 'none' → unset.
     */
    public static function status(string $key, string $configKey): string
    {
        $raw = AppSetting::query()->where('key', $key)->value('value');

        if (is_string($raw) && $raw !== '') {
            return 'custom';
        }

        return filled(config($configKey)) ? 'env' : 'none';
    }

    private static function stored(string $key): ?string
    {
        $raw = AppSetting::query()->where('key', $key)->value('value');

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        if (Str::startsWith($raw, self::ENC_PREFIX)) {
            try {
                return Crypt::decryptString(substr($raw, strlen(self::ENC_PREFIX)));
            } catch (\Throwable) {
                return null; // corrupted entry → fall through to env
            }
        }

        return $raw;
    }

    private static function storedOrConfig(string $key, string $configKey): ?string
    {
        $stored = self::stored($key);

        if ($stored !== null) {
            return $stored;
        }

        $config = config($configKey);

        return (is_string($config) && $config !== '') ? $config : null;
    }

    private static function looksLikeBcrypt(string $value): bool
    {
        return Str::startsWith($value, '$2y$')
            || Str::startsWith($value, '$2a$')
            || Str::startsWith($value, '$2b$');
    }
}
