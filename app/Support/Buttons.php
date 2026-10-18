<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Normalizes inline message buttons for the Zernio API.
 *
 * The API accepts at most three buttons with a 20-character title. URL
 * buttons must carry a valid absolute URL, and postback buttons must carry a
 * payload. Normalizing here prevents invalid or client-only fields from
 * reaching the API, where they would reject the whole message.
 */
final class Buttons
{
    /** Maximum number of inline buttons accepted by Instagram/Facebook. */
    public const LIMIT = 3;

    /** Maximum button title length accepted by Instagram/Facebook. */
    public const TITLE_LIMIT = 20;

    /** Maximum postback payload length. */
    public const PAYLOAD_LIMIT = 1000;

    /**
     * @return array<int, array<string, string>>
     *
     * @throws InvalidArgumentException when a URL button has no usable URL.
     */
    public static function normalize(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $title = mb_substr($title, 0, self::TITLE_LIMIT);
            $type = strtolower(trim((string) ($item['type'] ?? '')));

            if ($type === 'web_url') {
                $type = 'url';
            }

            $url = trim((string) ($item['url'] ?? $item['link'] ?? ''));

            if ($type === '') {
                $type = $url !== '' ? 'url' : 'postback';
            }

            if ($type === 'url') {
                $url = self::validUrl($url);

                if ($url === null) {
                    throw new InvalidArgumentException(
                        'لینک دکمه «'.$title.'» معتبر نیست؛ آدرس کامل را با https:// وارد کن.'
                    );
                }

                $button = ['type' => 'url', 'title' => $title, 'url' => $url];
            } elseif ($type === 'postback') {
                $payload = trim((string) ($item['payload'] ?? ''));
                $button = [
                    'type' => 'postback',
                    'title' => $title,
                    'payload' => $payload !== '' ? mb_substr($payload, 0, self::PAYLOAD_LIMIT) : $title,
                ];
            } elseif ($type === 'phone') {
                $phone = trim((string) ($item['phone'] ?? ''));

                if ($phone === '') {
                    continue;
                }

                $button = ['type' => 'phone', 'title' => $title, 'phone' => $phone];
            } else {
                continue;
            }

            $workflowId = trim((string) ($item['workflowId'] ?? $item['workflow_id'] ?? ''));

            if ($workflowId !== '') {
                $button['workflowId'] = $workflowId;
            }

            $normalized[] = $button;

            if (count($normalized) >= self::LIMIT) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * Normalize the `buttons` key of an outgoing API payload.
     */
    public static function attach(array $payload): array
    {
        if (! array_key_exists('buttons', $payload)) {
            return $payload;
        }

        $buttons = self::normalize($payload['buttons']);

        if ($buttons === []) {
            unset($payload['buttons']);
        } else {
            $payload['buttons'] = $buttons;
        }

        return $payload;
    }

    private static function validUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        // Accept "example.com/page" from the quick composer and upgrade it.
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.ltrim($url, '/');
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
