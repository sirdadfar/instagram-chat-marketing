<?php

namespace Tests\Unit;

use App\Support\QuickReplies;
use PHPUnit\Framework\TestCase;

class QuickRepliesTest extends TestCase
{
    public function test_text_quick_reply_payload_defaults_to_the_title(): void
    {
        $result = QuickReplies::normalize([['title' => 'Yes']]);

        $this->assertSame('Yes', $result[0]['title']);
        $this->assertSame('Yes', $result[0]['payload']);
    }

    public function test_a_custom_payload_is_preserved(): void
    {
        $result = QuickReplies::normalize([['title' => 'Yes', 'payload' => 'yes_v1']]);

        $this->assertSame('yes_v1', $result[0]['payload']);
    }

    public function test_titles_are_truncated_to_the_instagram_limit(): void
    {
        $result = QuickReplies::normalize([['title' => str_repeat('a', 40)]]);

        $this->assertSame(QuickReplies::TITLE_LIMIT, mb_strlen($result[0]['title']));
    }

    public function test_payloads_are_truncated_to_the_instagram_limit(): void
    {
        $result = QuickReplies::normalize([[
            'title' => 'Yes',
            'payload' => str_repeat('b', QuickReplies::PAYLOAD_LIMIT + 50),
        ]]);

        $this->assertSame(QuickReplies::PAYLOAD_LIMIT, mb_strlen($result[0]['payload']));
    }

    public function test_the_number_of_items_is_capped(): void
    {
        $items = array_fill(0, 20, ['title' => 'x']);

        $this->assertCount(QuickReplies::LIMIT, QuickReplies::normalize($items));
    }

    public function test_items_without_a_title_are_dropped(): void
    {
        $this->assertSame([], QuickReplies::normalize([['title' => '   '], 'nope']));
    }

    public function test_attach_removes_an_empty_quick_replies_key(): void
    {
        $payload = QuickReplies::attach(['message' => 'hi', 'quickReplies' => []]);

        $this->assertArrayNotHasKey('quickReplies', $payload);
    }

    public function test_attach_keeps_a_payload_without_quick_replies_untouched(): void
    {
        $payload = ['message' => 'hi'];

        $this->assertSame($payload, QuickReplies::attach($payload));
    }

    public function test_non_array_input_returns_an_empty_list(): void
    {
        $this->assertSame([], QuickReplies::normalize('not-an-array'));
    }

    public function test_only_the_outgoing_api_fields_are_kept(): void
    {
        $result = QuickReplies::normalize([[
            'content_type' => 'text',
            'title' => 'Yes',
            'payload' => 'yes_v1',
            'image_url' => 'https://example.com/icon.png',
        ]]);

        $this->assertArrayNotHasKey('content_type', $result[0]);
        $this->assertSame('https://example.com/icon.png', $result[0]['imageUrl']);
        $this->assertSame(['title', 'payload', 'imageUrl'], array_keys($result[0]));
    }
}
