<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;
use App\Models\Automation;

/**
 * Shared follow-gate decisions and copy.
 *
 * Global settings are the default for every trigger. Comment automations may
 * override both the gate switch and its copy through `settings`; other
 * triggers deliberately ignore those per-automation values.
 */
final class FollowGate
{
    public const DEFAULT_MESSAGE = 'دوست خوبم حتماً باید پیج رو فالو داشته باشی تا بتونیم بهت پیام بدیم.';

    public const DEFAULT_BUTTON_LABEL = 'فالو کردم ✓';

    public const DEFAULT_NOT_FOLLOWING_MESSAGE = 'هنوز فالو کردن پیج برای من قابل تأیید نیست. لطفاً پیج رو فالو کن و دوباره روی «فالو کردم» بزن.';

    public const DEFAULT_UNKNOWN_MESSAGE = 'فعلاً وضعیت فالو قابل بررسی نیست. چند لحظه بعد دوباره روی «فالو کردم» بزن.';

    public const DEFAULT_CONFIRMED_MESSAGE = 'فالو شما تأیید شد ✅ ادمین پیج به‌زودی بهتون پاسخ می‌ده.';

    /**
     * Whether the follow gate should run for this automation.
     */
    public static function enabled(Automation $a): bool
    {
        $settings = is_array($a->settings) ? $a->settings : [];

        // Only comment (or multi-trigger) automations persist an authoritative
        // per-automation gate flag; for single story replies the stored value is
        // always false and not authoritative.
        if ((self::trigger($a) === 'comment' || $a->hasMultipleTriggers()) && array_key_exists('followGateEnabled', $settings)) {
            return self::toBool($settings['followGateEnabled']);
        }

        return (bool) AppSetting::getValue('follow_gate_enabled', true);
    }

    /**
     * Whether keyword-less messages should still reach the gate.
     */
    public static function withoutKeywordsEnabled(): bool
    {
        return (bool) AppSetting::getValue('follow_gate_without_keywords', false);
    }

    /**
     * An automation that explicitly targets non-followers must never ask them
     * to follow; otherwise it would gate away its entire audience.
     */
    public static function targetsNonFollowers(Automation $a): bool
    {
        $audience = is_array($a->audience) ? $a->audience : [];

        return ($audience['followerStatus'] ?? null) === 'non_follower';
    }

    /**
     * Resolved gate copy for an automation.
     *
     * @return array{message: string, buttonLabel: string, notFollowingMessage: string, unknownMessage: string, confirmedMessage: string}
     */
    public static function config(Automation $a): array
    {
        $settings = is_array($a->settings) ? $a->settings : [];
        // The per-automation gate copy is only editable for comment (or
        // multi-trigger) automations; other triggers follow the global settings
        // so edits apply everywhere.
        $local = (self::trigger($a) === 'comment' || $a->hasMultipleTriggers()) && is_array($settings['followGate'] ?? null)
            ? $settings['followGate']
            : [];

        return [
            'message' => self::pick($local['message'] ?? null, 'follow_gate_message', self::DEFAULT_MESSAGE),
            'buttonLabel' => self::pick($local['buttonLabel'] ?? null, 'follow_gate_button_label', self::DEFAULT_BUTTON_LABEL),
            'notFollowingMessage' => self::pick($local['notFollowingMessage'] ?? null, 'follow_gate_not_following_message', self::DEFAULT_NOT_FOLLOWING_MESSAGE),
            // "Unknown" must never reuse the must-follow copy: only an explicit
            // unknown message (then the global one) may be sent for null status.
            'unknownMessage' => self::pick(
                $local['unknownMessage'] ?? null,
                'follow_gate_unknown_message',
                self::DEFAULT_UNKNOWN_MESSAGE
            ),
            // Sent after a successful follow confirmation when there is no
            // automation reply to resume (or it produced nothing to send).
            'confirmedMessage' => self::pick(
                $local['confirmedMessage'] ?? null,
                'follow_gate_confirmed_message',
                self::DEFAULT_CONFIRMED_MESSAGE
            ),
        ];
    }

    /**
     * Same as config(), for callers without an Automation (e.g. a webhook whose
     * automation was deleted).
     *
     * @return array{message: string, buttonLabel: string, notFollowingMessage: string, unknownMessage: string, confirmedMessage: string}
     */
    public static function configForSettings(array $settings = []): array
    {
        $automation = new Automation;
        $automation->settings = $settings;

        return self::config($automation);
    }

    private static function trigger(Automation $a): string
    {
        return $a->trigger instanceof \BackedEnum ? (string) $a->trigger->value : (string) $a->trigger;
    }

    private static function pick(mixed $local, string $settingKey, string $default): string
    {
        $value = self::text($local);

        if ($value !== '') {
            return $value;
        }

        $value = self::text(AppSetting::getValue($settingKey, $default));

        return $value !== '' ? $value : $default;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }

        if (is_string($value)) {
            return filter_var(trim($value), FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) $value;
    }
}
