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
 * Resolves the Zernio service key.
 *
 * Every user MUST set their own personal key (encrypted in DB). There is no
 * global/.env fallback for service keys — each account is billed/bound to the
 * key of the page owner.
 */
final class Credentials
{
    /** Encrypted-at-rest marker prefix. */
    private const ENC_PREFIX = 'enc:';

    public static function zernioApiKey(?User $context = null): ?string
    {
        // Queue jobs must use the owner of the Instagram page being processed.
        // Older installations may still contain a plaintext key from before
        // credentials were encrypted, so resolve both storage formats safely.
        if ($context !== null) {
            $key = self::userApiKey($context);
            if ($key !== null) {
                return $key;
            }
        }

        // Web requests use the signed-in user's personal key.
        $user = auth()->user();
        if ($user instanceof User) {
            $key = self::userApiKey($user);
            if ($key !== null) {
                return $key;
            }
        }

        // Keep the documented server key as a fallback for legacy deployments.
        $fallback = config('zernio.api_key');

        return is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : null;
    }

    private static function userApiKey(User $user): ?string
    {
        $raw = $user->zernio_api_key_enc;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        // New records use encrypted values with the explicit marker.
        if (Str::startsWith($raw, self::ENC_PREFIX)) {
            return self::decrypt($raw);
        }

        // Backward compatibility for keys saved before encryption was introduced.
        return trim($raw);
    }

    /** Stores (or replaces) the service key on the given user. */
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

    /** Stores (or replaces) the per-user inbound webhook signing secret. */
    public static function rememberUserWebhookSecret(User $user, mixed $plain): void
    {
        $value = trim((string) $plain);

        if ($value === '') {
            return;
        }

        $user->forceFill([
            'zernio_webhook_secret_enc' => self::ENC_PREFIX . Crypt::encryptString($value),
        ])->save();
    }

    /** Decrypts the user's own webhook signing secret, or null when unset. */
    public static function userWebhookSecret(User $user): ?string
    {
        if (is_string($user->zernio_webhook_secret_enc) && $user->zernio_webhook_secret_enc !== '') {
            return self::decrypt($user->zernio_webhook_secret_enc);
        }

        return null;
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

    /** Status of the effective Zernio key for the session user ('personal'|'none'). */
    public static function zernioKeyStatus(): string
    {
        $user = auth()->user();
        if ($user instanceof User && filled($user->zernio_api_key_enc)) {
            return 'personal';
        }

        return 'none';
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
