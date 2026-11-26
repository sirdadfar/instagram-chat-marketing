<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalizes a follow-status payload into a tri-state value.
 *
 * Zernio/Instagram may return the flag at the top level or nested under
 * `data`, `profile`, `instagramProfile`, ... and may use `isFollower` or
 * `is_follower`. A missing or empty value means UNKNOWN — never "not a
 * follower" — so callers must treat `null` as "verify later".
 */
final class FollowStatus
{
    /** Keys that may wrap the real profile object. */
    private const CONTAINER_KEYS = [
        'data',
        'profile',
        'instagramprofile',
        'author',
        'sender',
        'contact',
    ];

    /** Nesting levels allowed while looking for the flag. */
    private const MAX_DEPTH = 3;

    public static function from(mixed $payload): ?bool
    {
        return self::read($payload, 0);
    }

    private static function read(mixed $payload, int $depth): ?bool
    {
        if (! is_array($payload)) {
            return self::scalar($payload);
        }

        foreach ($payload as $key => $value) {
            if (is_string($key) && self::normalizeKey($key) === 'isfollower') {
                $result = self::scalar($value);

                if ($result !== null) {
                    return $result;
                }
            }
        }

        if ($depth >= self::MAX_DEPTH) {
            return null;
        }

        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! is_array($value)) {
                continue;
            }

            if (! in_array(self::normalizeKey($key), self::CONTAINER_KEYS, true)) {
                continue;
            }

            $result = self::read($value, $depth + 1);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    private static function scalar(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return match ($value) {
                1 => true,
                0 => false,
                default => null,
            };
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true' => true,
                '0', 'false' => false,
                default => null,
            };
        }

        return null;
    }

    private static function normalizeKey(string $key): string
    {
        return strtolower(str_replace(['_', '-', ' '], '', $key));
    }
}
