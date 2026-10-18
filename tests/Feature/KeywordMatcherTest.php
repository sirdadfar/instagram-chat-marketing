<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\InstagramAccount;
use App\Services\Automation\KeywordMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeywordMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function automation(string $matchMode = 'any', bool $excluded = false): Automation
    {
        $account = InstagramAccount::create([
            'zernio_account_id' => 'acc_'.fake()->unique()->numberBetween(1, 99999),
            'status' => 'active',
        ]);

        return Automation::create([
            'instagram_account_id' => $account->id,
            'name' => 'Keyword rule',
            'trigger' => 'comment',
            'status' => 'active',
            'match_mode' => $matchMode,
        ]);
    }

    public function test_contains_match_passes_when_a_keyword_is_present(): void
    {
        $automation = $this->automation();
        $automation->keywords()->create(['keyword' => 'price', 'match_type' => 'contains', 'is_excluded' => false]);

        $result = app(KeywordMatcher::class)->match($automation->fresh(), 'What is the price?');

        $this->assertTrue($result['pass']);
    }

    public function test_all_mode_requires_every_keyword(): void
    {
        $automation = $this->automation('all');
        $automation->keywords()->createMany([
            ['keyword' => 'price', 'match_type' => 'contains', 'is_excluded' => false],
            ['keyword' => 'size', 'match_type' => 'contains', 'is_excluded' => false],
        ]);

        $partial = app(KeywordMatcher::class)->match($automation->fresh(), 'price only');
        $this->assertFalse($partial['pass']);

        $full = app(KeywordMatcher::class)->match($automation->fresh(), 'price and size');
        $this->assertTrue($full['pass']);
    }

    public function test_excluded_keywords_block_the_match(): void
    {
        $automation = $this->automation();
        $automation->keywords()->createMany([
            ['keyword' => 'price', 'match_type' => 'contains', 'is_excluded' => false],
            ['keyword' => 'refund', 'match_type' => 'contains', 'is_excluded' => true],
        ]);

        $result = app(KeywordMatcher::class)->match($automation->fresh(), 'price and refund policy');

        $this->assertFalse($result['pass']);
        $this->assertContains('refund', $result['excluded']);
    }

    public function test_exact_mode_requires_a_full_match(): void
    {
        $automation = $this->automation();
        $automation->keywords()->create(['keyword' => 'info', 'match_type' => 'exact', 'is_excluded' => false]);

        $this->assertTrue(app(KeywordMatcher::class)->match($automation->fresh(), 'info')['pass']);
        $this->assertFalse(app(KeywordMatcher::class)->match($automation->fresh(), 'more info')['pass']);
    }
}
