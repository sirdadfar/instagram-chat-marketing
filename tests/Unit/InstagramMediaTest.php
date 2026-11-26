<?php

namespace Tests\Unit;

use App\Support\InstagramMedia;
use PHPUnit\Framework\TestCase;

class InstagramMediaTest extends TestCase
{
    public function test_native_platform_posts_use_picture_message_and_created_time(): void
    {
        $items = InstagramMedia::posts([
            'status' => 'success',
            'posts' => [[
                'id' => '1789',
                'platform' => 'instagram',
                'message' => 'کپشن پست',
                'createdTime' => '2026-11-20T10:00:00Z',
                'permalink' => 'https://www.instagram.com/p/abc/',
                'picture' => 'https://cdn.example.com/post.jpg',
                'mediaType' => 'IMAGE',
            ]],
        ]);

        $this->assertCount(1, $items);

        $post = $items->first();
        $this->assertSame('1789', $post['id']);
        $this->assertSame('https://cdn.example.com/post.jpg', $post['thumbnailUrl']);
        $this->assertSame('کپشن پست', $post['caption']);
        $this->assertSame('2026-11-20T10:00:00Z', $post['timestamp']);
        $this->assertSame('published', $post['status']);
        $this->assertSame('تصویر', $post['label']);
    }

    public function test_zernio_post_schema_is_still_supported(): void
    {
        $items = InstagramMedia::posts([
            'data' => [
                'posts' => [[
                    'platformPostId' => '99',
                    'mediaType' => 'video',
                    'content' => 'متن ذخیره‌شده',
                    'publishedAt' => '2026-11-21T08:30:00Z',
                    'mediaItems' => [[
                        'type' => 'video',
                        'url' => 'https://cdn.example.com/video.mp4',
                        'thumbnailUrl' => 'https://cdn.example.com/thumb.jpg',
                    ]],
                ]],
            ],
        ]);

        $this->assertCount(1, $items);

        $post = $items->first();
        $this->assertSame('99', $post['id']);
        $this->assertSame('https://cdn.example.com/thumb.jpg', $post['thumbnailUrl']);
        $this->assertSame('https://cdn.example.com/video.mp4', $post['mediaUrl']);
        $this->assertSame('متن ذخیره‌شده', $post['caption']);
    }

    public function test_native_reels_are_labelled_as_reels(): void
    {
        $items = InstagramMedia::posts([
            'posts' => [[
                'id' => 'reel-1',
                'mediaType' => 'VIDEO',
                'mediaProductType' => 'REELS',
                'picture' => 'https://cdn.example.com/reel.jpg',
                'createdTime' => '2026-11-22T12:00:00Z',
            ]],
        ]);

        $this->assertSame('REEL', $items->first()['mediaType']);
        $this->assertSame('ویدیو / ریلز', $items->first()['label']);
    }

    public function test_platform_posts_fall_back_to_the_media_url_when_no_picture(): void
    {
        $items = InstagramMedia::posts([
            'posts' => [[
                'id' => '10',
                'mediaType' => 'IMAGE',
                'media_url' => 'https://cdn.example.com/media.jpg',
            ]],
        ]);

        $this->assertSame('https://cdn.example.com/media.jpg', $items->first()['thumbnailUrl']);
    }

    public function test_stories_are_read_from_the_data_list(): void
    {
        $items = InstagramMedia::stories([
            'data' => [[
                'id' => 'story-1',
                'mediaType' => 'VIDEO',
                'mediaUrl' => 'https://cdn.example.com/story.mp4',
                'thumbnailUrl' => 'https://cdn.example.com/story.jpg',
                'permalink' => 'https://www.instagram.com/stories/abc/1/',
                'timestamp' => '2026-11-23T09:00:00Z',
            ]],
        ]);

        $this->assertCount(1, $items);

        $story = $items->first();
        $this->assertSame('story-1', $story['id']);
        $this->assertSame('VIDEO', $story['mediaType']);
        $this->assertSame('https://cdn.example.com/story.jpg', $story['thumbnailUrl']);
        $this->assertSame('https://cdn.example.com/story.mp4', $story['mediaUrl']);
    }

    public function test_empty_or_wrapped_responses_are_safe(): void
    {
        $this->assertCount(0, InstagramMedia::posts([]));
        $this->assertCount(0, InstagramMedia::posts(['data' => ['message' => 'not found']]));
        $this->assertCount(0, InstagramMedia::stories([]));
    }
}
