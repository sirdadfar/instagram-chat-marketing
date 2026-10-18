<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalizes Instagram quick reply items.
 *
 * The messaging API rejects text quick replies that do not include a
 * `payload` ("Quick reply payload is required"), and it only accepts the
 * `{ title, payload, imageUrl, workflowId }` shape. Every text quick reply is
 * therefore guaranteed to carry a payload derived from its title, and any
 * client-only fields (such as `content_type`) are dropped.
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

            $title = mb_substr($title, 0, self::TITLE_LIMIT);

            $quickReply = [
                'title' => $title,
                'payload' => $title,
            ];

            $payload = trim((string) ($item['payload'] ?? ''));
            if ($payload !== '') {
                $quickReply['payload'] = mb_substr($payload, 0, self::PAYLOAD_LIMIT);
            }

            $imageUrl = trim((string) ($item['imageUrl'] ?? $item['image_url'] ?? ''));
            if ($imageUrl !== '') {
                $quickReply['imageUrl'] = $imageUrl;
            }

            $workflowId = trim((string) ($item['workflowId'] ?? $item['workflow_id'] ?? ''));
            if ($workflowId !== '') {
                $quickReply['workflowId'] = $workflowId;
            }

            $normalized[] = $quickReply;

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
