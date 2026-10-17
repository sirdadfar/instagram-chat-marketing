<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;
use App\Models\InstagramAccount;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Resolves runtime credentials with this priority:
 *   1. The page/user owner's own key (set from the Settings screen, encrypted in DB).
 *   2. Value saved from the Settings screen by an admin (stored encrypted).
 *   3. Fallback from config (originally sourced from .env, cache-safe).
 */
final class Credentials
{
    /** Encrypted-at-rest marker prefix. */
    private const ENC_PREFIX = 'enc:';

    public static function zernioApiKey(?User $context = null): ?string
    {
        // 1) explicit context (jobs: page owner)
        if ($context !== null && is_string($context->zernio_api_key_enc) && $context->zernio_api_key_enc !== '') {
            return self::decrypt($context->zernio_api_key_enc);
        }

        // 2) session user's own key (web requests)
        $user = auth()->user();
        if ($user instanceof User && is_string($user->zernio_api_key_enc) && $user->zernio_api_key_enc !== '') {
            return self::decrypt($user->zernio_api_key_enc);
        }

        // 3) global: admin's Settings value, then .env
        return self::storedOrConfig('zernio_api_key', 'zernio.api_key');
    }

    /** Stores (or clears) the service key on the given user. */
    public static function rememberUserKey(User $user, mixed $plain): void
    {
        $value = trim((string) $plain);

        if ($value === '') {
            return;
        }

        if ($value === '__clear__') {
            $user->forceFill(['zernio_api_key_enc' => null])->save();
            return;
        }

        $user->forceFill([
            'zernio_api_key_enc' => self::ENC_PREFIX . Crypt::encryptString($value),
        ])->save();
    }

    /** Webhook secret is server-wide; only admins manage it. */
    public static function setGlobalWebhookSecret(mixed $plain): void
    {
        self::remember('zernio_webhook_secret', $plain);
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

    /** Status of the effective Zernio key for the session user ('personal'|'custom'|'env'|'none'). */
    public static function zernioKeyStatus(string $configKey = 'zernio.api_key'): string
    {
        $user = auth()->user();
        if ($user instanceof User && filled($user->zernio_api_key_enc)) {
            return 'personal';
        }

        return self::status('zernio_api_key', $configKey);
    }

    private static function stored(string $key): ?string
    {
        $raw = AppSetting::query()->where('key', $key)->value('value');

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        if (Str::startsWith($raw, self::ENC_PREFIX)) {
            return self::decrypt($raw);
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

    private static function decrypt(string $raw): ?string
    {
        try {
            return Crypt::decryptString(substr($raw, strlen(self::ENC_PREFIX)));
        } catch (\Throwable) {
            return null; // corrupted entry → fall back to next source
        }
    }

    private static function looksLikeBcrypt(string $value): bool
    {
        return Str::startsWith($value, '$2y$')
            || Str::startsWith($value, '$2a$')
            || Str::startsWith($value, '$2b$');
    }
}
