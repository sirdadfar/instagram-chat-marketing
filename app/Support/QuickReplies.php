<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalizes Instagram quick reply items.
 *
 * The messaging API rejects text quick replies that do not include a
 * `payload` ("Quick reply payload is required"), even though the payload is
 * optional in some client libraries. Every text quick reply is therefore
 * guaranteed to carry a payload derived from its title.
 */
final class QuickReplies
{
    /** Maximum number of quick replies accepted by Instagram. */
    public const LIMIT = 13;

    /** Maximum quick reply title length accepted by Instagram. */
    public const TITLE_LIMIT = 20;

    /** Maximum quick reply payload length accepted by Instagram. */
    public const PAYLOAD_LIMIT = 1000;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function normalize(mixed $items, int $limit = self::LIMIT): array
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

            $contentType = trim((string) ($item['content_type'] ?? $item['contentType'] ?? 'text'));
            if ($contentType === '') {
                $contentType = 'text';
            }

            unset($item['contentType']);

            $item['content_type'] = $contentType;
            $item['title'] = mb_substr($title, 0, self::TITLE_LIMIT);

            // Quick replies such as `user_email` and `user_phone_number` do not
            // use a payload; plain text quick replies are required to have one.
            if ($contentType === 'text') {
                $payload = trim((string) ($item['payload'] ?? ''));
                $item['payload'] = $payload !== ''
                    ? mb_substr($payload, 0, self::PAYLOAD_LIMIT)
                    : $item['title'];
            }

            $normalized[] = $item;

            if (count($normalized) >= $limit) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * Normalize the `quickReplies` key of an outgoing API payload.
     */
    public static function attach(array $payload): array
    {
        if (! array_key_exists('quickReplies', $payload)) {
            return $payload;
        }

        $quickReplies = self::normalize($payload['quickReplies']);

        if ($quickReplies === []) {
            unset($payload['quickReplies']);
        } else {
            $payload['quickReplies'] = $quickReplies;
        }

        return $payload;
    }
}
