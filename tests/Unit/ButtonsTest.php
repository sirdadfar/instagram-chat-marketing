<?php

namespace Tests\Unit;

use App\Support\Buttons;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ButtonsTest extends TestCase
{
    public function test_a_url_without_a_scheme_is_upgraded_to_https(): void
    {
        $result = Buttons::normalize([[
            'type' => 'url',
            'title' => 'Site',
            'url' => 'example.com/page',
        ]]);

        $this->assertSame('https://example.com/page', $result[0]['url']);
        $this->assertSame(['type', 'title', 'url'], array_keys($result[0]));
    }

    public function test_web_url_is_aliased_to_url(): void
    {
        $result = Buttons::normalize([[
            'type' => 'web_url',
            'title' => 'Site',
            'url' => 'https://example.com',
        ]]);

        $this->assertSame('url', $result[0]['type']);
    }

    public function test_a_postback_payload_defaults_to_the_title(): void
    {
        $result = Buttons::normalize([['type' => 'postback', 'title' => 'Yes']]);

        $this->assertSame('Yes', $result[0]['payload']);
        $this->assertSame('postback', $result[0]['type']);
    }

    public function test_titles_are_truncated_and_the_number_of_buttons_is_capped(): void
    {
        $items = array_fill(0, 6, ['type' => 'postback', 'title' => str_repeat('a', 40)]);
        $result = Buttons::normalize($items);

        $this->assertCount(Buttons::LIMIT, $result);
        $this->assertSame(Buttons::TITLE_LIMIT, mb_strlen($result[0]['title']));
    }

    public function test_an_invalid_url_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Buttons::normalize([['type' => 'url', 'title' => 'Site', 'url' => 'not a url']]);
    }

    public function test_client_only_fields_are_dropped(): void
    {
        $result = Buttons::normalize([[
            'type' => 'postback',
            'title' => 'Yes',
            'payload' => 'yes_v1',
            'content_type' => 'text',
            'url' => '',
        ]]);

        $this->assertSame(['type', 'title', 'payload'], array_keys($result[0]));
    }

    public function test_attach_removes_an_empty_buttons_key(): void
    {
        $payload = Buttons::attach(['message' => 'hi', 'buttons' => []]);

        $this->assertArrayNotHasKey('buttons', $payload);
    }

    public function test_attach_keeps_a_payload_without_buttons_untouched(): void
    {
        $payload = ['message' => 'hi'];

        $this->assertSame($payload, Buttons::attach($payload));
    }

    public function test_non_array_input_returns_an_empty_list(): void
    {
        $this->assertSame([], Buttons::normalize('not-an-array'));
    }
}
