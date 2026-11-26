<?php

namespace Tests\Unit;

use App\Support\FollowStatus;
use PHPUnit\Framework\TestCase;

class FollowStatusTest extends TestCase
{
    public function test_it_reads_the_top_level_flag(): void
    {
        $this->assertTrue(FollowStatus::from(['isFollower' => true]));
        $this->assertFalse(FollowStatus::from(['isFollower' => false]));
        $this->assertNull(FollowStatus::from(['isFollower' => null]));
    }

    public function test_it_reads_a_nested_data_flag(): void
    {
        $this->assertTrue(FollowStatus::from(['data' => ['isFollower' => true]]));
        $this->assertFalse(FollowStatus::from(['data' => ['isFollower' => false]]));
    }

    public function test_it_reads_nested_profile_and_instagram_profile_flags(): void
    {
        $this->assertTrue(FollowStatus::from(['profile' => ['isFollower' => true]]));
        $this->assertFalse(FollowStatus::from(['instagramProfile' => ['is_follower' => false]]));
        $this->assertTrue(FollowStatus::from(['instagram_profile' => ['data' => ['isFollower' => true]]]));
    }

    public function test_it_reads_snake_case_keys(): void
    {
        $this->assertTrue(FollowStatus::from(['is_follower' => true]));
        $this->assertFalse(FollowStatus::from(['is_follower' => false]));
    }

    public function test_it_accepts_string_and_integer_booleans(): void
    {
        $this->assertTrue(FollowStatus::from('true'));
        $this->assertTrue(FollowStatus::from('TRUE'));
        $this->assertTrue(FollowStatus::from('1'));
        $this->assertTrue(FollowStatus::from(1));
        $this->assertFalse(FollowStatus::from('false'));
        $this->assertFalse(FollowStatus::from('FALSE'));
        $this->assertFalse(FollowStatus::from('0'));
        $this->assertFalse(FollowStatus::from(0));
        $this->assertTrue(FollowStatus::from(['isFollower' => 'true']));
        $this->assertFalse(FollowStatus::from(['data' => ['isFollower' => '0']]));
    }

    public function test_a_missing_flag_is_unknown_not_false(): void
    {
        $this->assertNull(FollowStatus::from([]));
        $this->assertNull(FollowStatus::from(null));
        $this->assertNull(FollowStatus::from(['username' => 'someone']));
        $this->assertNull(FollowStatus::from(['data' => ['username' => 'someone']]));
        $this->assertNull(FollowStatus::from('unknown'));
    }

    public function test_consent_required_without_a_flag_is_unknown(): void
    {
        $payload = [
            'unavailableReason' => 'consent_required',
            'isFollower' => null,
        ];

        $this->assertNull(FollowStatus::from($payload));
        $this->assertNull(FollowStatus::from(['data' => ['unavailableReason' => 'consent_required']]));
    }

    public function test_it_stops_at_the_depth_limit(): void
    {
        $payload = ['data' => ['data' => ['data' => ['data' => ['isFollower' => true]]]]];

        $this->assertNull(FollowStatus::from($payload));
    }
}
