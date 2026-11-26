<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Normalises the different shapes Zernio returns for Instagram content.
 *
 * The platform posts endpoint (`/accounts/{accountId}/posts`) returns native
 * fields such as `picture`, `message` and `createdTime`, while the
 * sync-external fallback and the Zernio posts API return `thumbnailUrl`,
 * `content` and `mediaItems`. The panel expects one shape everywhere, so both
 * responses are funnelled through this class.
 */
final class InstagramMedia
{
    /**
     * @return Collection<int, array{
     *     id: string,
     *     mediaType: string,
     *     mediaProductType: string,
     *     label: string,
     *     thumbnailUrl: string,
     *     mediaUrl: string,
     *     permalink: string,
     *     caption: string,
     *     timestamp: string,
     *     status: string
     * }>
     */
    public static function posts(array $response): Collection
    {
        return collect(self::items($response, 'posts'))
            ->filter(fn ($item): bool => is_array($item))
            ->map(static function (array $item): array {
                $id = self::text($item['id']
                    ?? $item['platformPostId']
                    ?? $item['platform_post_id']
                    ?? $item['_id']
                    ?? '');

                $type = strtoupper(self::text($item['mediaType']
                    ?? $item['media_type']
                    ?? data_get($item, 'mediaItems.0.type')
                    ?? 'IMAGE'));

                $productType = strtoupper(self::text($item['mediaProductType']
                    ?? $item['media_product_type']
                    ?? ''));

                if ($productType === 'REELS') {
                    $type = 'REEL';
                }

                $mediaUrl = self::text($item['mediaUrl']
                    ?? $item['media_url']
                    ?? data_get($item, 'mediaItems.0.url')
                    ?? data_get($item, 'mediaItems.0.mediaUrl')
                    ?? '');

                $thumbnail = self::firstText([
                    $item['thumbnailUrl'] ?? null,
                    $item['thumbnail_url'] ?? null,
                    // Native platform posts return their cover/image as `picture`.
                    $item['picture'] ?? null,
                    $item['imageUrl'] ?? null,
                    $item['image_url'] ?? null,
                    data_get($item, 'mediaItems.0.thumbnailUrl'),
                    data_get($item, 'mediaItems.0.thumbnail_url'),
                    data_get($item, 'mediaItems.0.thumbnail'),
                    data_get($item, 'mediaItems.0.url'),
                    $mediaUrl,
                ]);

                $caption = self::firstText([
                    $item['caption'] ?? null,
                    // Native platform posts call the caption `message`.
                    $item['message'] ?? null,
                    $item['content'] ?? null,
                    $item['text'] ?? null,
                ]);

                $timestamp = self::firstText([
                    // Native platform posts use `createdTime`.
                    $item['createdTime'] ?? null,
                    $item['timestamp'] ?? null,
                    $item['publishedAt'] ?? null,
                    $item['published_at'] ?? null,
                    $item['createdAt'] ?? null,
                ]);

                $status = strtolower(self::text($item['status'] ?? $item['state'] ?? ''));
                if ($status === '') {
                    $status = ! empty($item['scheduledFor'] ?? $item['scheduled_for'] ?? null)
                        ? 'scheduled'
                        : ($timestamp !== '' ? 'published' : 'draft');
                }

                return [
                    'id' => $id,
                    'mediaType' => $type,
                    'mediaProductType' => $productType,
                    'label' => self::label($type),
                    'thumbnailUrl' => $thumbnail,
                    'mediaUrl' => $mediaUrl,
                    'permalink' => self::text($item['permalink'] ?? $item['platformPostUrl'] ?? $item['platform_post_url'] ?? ''),
                    'caption' => $caption,
                    'timestamp' => $timestamp,
                    'status' => $status,
                ];
            })
            ->filter(fn (array $item): bool => $item['id'] !== '')
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     id: string,
     *     mediaType: string,
     *     mediaProductType: string,
     *     label: string,
     *     thumbnailUrl: string,
     *     mediaUrl: string,
     *     permalink: string,
     *     caption: string,
     *     timestamp: string,
     *     status: string
     * }>
     */
    public static function stories(array $response): Collection
    {
        return collect(self::items($response, 'stories'))
            ->filter(fn ($item): bool => is_array($item))
            ->map(static function (array $item): array {
                $type = strtoupper(self::text($item['mediaType'] ?? $item['media_type'] ?? 'IMAGE'));

                $mediaUrl = self::text($item['mediaUrl']
                    ?? $item['media_url']
                    ?? data_get($item, 'mediaItems.0.url')
                    ?? '');

                $thumbnail = self::firstText([
                    $item['thumbnailUrl'] ?? null,
                    $item['thumbnail_url'] ?? null,
                    $item['picture'] ?? null,
                    $item['imageUrl'] ?? null,
                    $item['image_url'] ?? null,
                    data_get($item, 'mediaItems.0.thumbnailUrl'),
                    data_get($item, 'mediaItems.0.thumbnail_url'),
                    $mediaUrl,
                ]);

                return [
                    'id' => self::text($item['id'] ?? $item['mediaId'] ?? $item['_id'] ?? ''),
                    'mediaType' => $type,
                    'mediaProductType' => strtoupper(self::text($item['mediaProductType'] ?? $item['media_product_type'] ?? 'STORY')),
                    'label' => self::label($type),
                    'thumbnailUrl' => $thumbnail,
                    'mediaUrl' => $mediaUrl,
                    'permalink' => self::text($item['permalink'] ?? ''),
                    'caption' => self::firstText([$item['caption'] ?? null, $item['message'] ?? null, $item['content'] ?? null]),
                    'timestamp' => self::firstText([
                        $item['timestamp'] ?? null,
                        $item['createdTime'] ?? null,
                        $item['publishedAt'] ?? null,
                        $item['published_at'] ?? null,
                    ]),
                    'status' => 'published',
                ];
            })
            ->filter(fn (array $item): bool => $item['id'] !== '')
            ->unique('id')
            ->values();
    }

    /**
     * Pull the item list out of the response wrapper Zernio happens to use.
     *
     * @return array<int, mixed>
     */
    private static function items(array $response, string $key): array
    {
        $items = $response[$key] ?? data_get($response, 'data.' . $key);

        if (! is_array($items)) {
            $data = $response['data'] ?? null;
            // Some endpoints (Instagram stories) return the list directly
            // under `data` instead of `data.stories`.
            $items = is_array($data) && array_is_list($data) ? $data : [];
        }

        return $items;
    }

    private static function label(string $type): string
    {
        return match (true) {
            in_array($type, ['CAROUSEL_ALBUM', 'CAROUSEL'], true) => 'آلبوم',
            in_array($type, ['VIDEO', 'REEL'], true) => 'ویدیو / ریلز',
            default => 'تصویر',
        };
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private static function firstText(array $values): string
    {
        foreach ($values as $value) {
            $text = self::text($value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
