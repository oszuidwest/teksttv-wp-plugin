<?php

namespace TekstTV\Tests\Unit\Blocks\Loop;

use Brain\Monkey\Functions;
use TekstTV\Blocks\Loop\CampaignLoopBlock;
use TekstTV\Tests\Unit\TestCase;

class CampaignLoopBlockTest extends TestCase
{
    public function test_save_with_groups(): void
    {
        // Groups are stored by stable id, not by label.
        $result = CampaignLoopBlock::save([
            'groups' => ['grp_aaa111', 'grp_bbb222'],
            'intro_image_id' => '10',
            'outro_image_id' => '20',
            'limit' => '5',
        ]);

        $this->assertSame(['grp_aaa111', 'grp_bbb222'], $result['groups']);
        $this->assertSame(10, $result['intro_image_id']);
        $this->assertSame(20, $result['outro_image_id']);
        $this->assertArrayNotHasKey('limit', $result);
    }

    public function test_save_filters_empty_groups(): void
    {
        $result = CampaignLoopBlock::save([
            'groups' => ['grp_aaa111', '', 'grp_bbb222'],
        ]);

        $this->assertSame(['grp_aaa111', 'grp_bbb222'], $result['groups']);
    }

    public function test_save_empty_groups_defaults(): void
    {
        $result = CampaignLoopBlock::save([]);

        $this->assertSame([], $result['groups']);
        $this->assertSame(0, $result['intro_image_id']);
        $this->assertSame(0, $result['outro_image_id']);
    }

    public function test_save_non_array_groups(): void
    {
        $result = CampaignLoopBlock::save(['groups' => 'single']);

        $this->assertSame([], $result['groups']);
    }

    public function test_build_returns_empty_when_no_groups(): void
    {
        $block = ['groups' => []];
        $this->assertSame([], CampaignLoopBlock::build($block, 'tv1'));
    }

    public function test_build_with_campaigns(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([
                [
                    'channels' => ['tv1'],
                    'group' => 'sponsors',
                    'date_start' => '2026-04-01',
                    'date_end' => '2026-04-30',
                    'duration' => 5,
                    'slides' => [100, 101],
                ],
                [
                    'channels' => ['tv1'],
                    'group' => 'sponsors',
                    'duration' => 9,
                    'slides' => [200, 201],
                ],
            ]);
        Functions\expect('wp_get_attachment_url')
            ->andReturnUsing(fn ($id) => 'https://example.com/img-' . $id . '.jpg');

        // 'limit' is a legacy key from the removed slide-limit feature. Both
        // complete campaigns coming back in their saved order proves it is inert.
        $block = ['groups' => ['sponsors'], 'limit' => 1];
        $result = CampaignLoopBlock::build($block, 'tv1');

        $this->assertCount(4, $result);
        $this->assertSame(['commercial', 'commercial', 'commercial', 'commercial'], array_column($result, 'type'));
        $this->assertSame([5000, 5000, 9000, 9000], array_column($result, 'duration'));
        $this->assertSame([
            'https://example.com/img-100.jpg',
            'https://example.com/img-101.jpg',
            'https://example.com/img-200.jpg',
            'https://example.com/img-201.jpg',
        ], array_column($result, 'url'));
    }

    public function test_build_filters_by_channel(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([
                [
                    'channels' => ['tv2'],
                    'group' => 'sponsors',
                    'slides' => [100],
                ],
            ]);

        $block = ['groups' => ['sponsors']];
        $result = CampaignLoopBlock::build($block, 'tv1');

        $this->assertSame([], $result);
    }

    public function test_build_intro_outro(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([
                [
                    'channels' => ['tv1'],
                    'group' => 'sponsors',
                    'slides' => [100],
                    'duration' => 5,
                ],
            ]);
        Functions\expect('wp_get_attachment_url')
            ->andReturnUsing(fn ($id) => 'https://example.com/img-' . $id . '.jpg');

        $block = [
            'groups' => ['sponsors'],
            'intro_image_id' => 50,
            'outro_image_id' => 51,
        ];
        $result = CampaignLoopBlock::build($block, 'tv1');

        $this->assertCount(3, $result);
        $this->assertSame('commercial_transition', $result[0]['type']);
        $this->assertSame(5000, $result[0]['duration']);
        $this->assertSame('commercial', $result[1]['type']);
        $this->assertSame('commercial_transition', $result[2]['type']);
    }

    public function test_build_no_intro_outro_when_no_matching_campaigns(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([]);

        $block = [
            'groups' => ['sponsors'],
            'intro_image_id' => 50,
            'outro_image_id' => 51,
        ];
        $result = CampaignLoopBlock::build($block, 'tv1');

        $this->assertSame([], $result);
    }

    public function test_build_uses_default_duration(): void
    {
        Functions\when('get_option')->alias(function (string $name, $default = false) {
            if ($name === 'teksttv_campaigns') {
                return [
                    [
                        'channels' => ['tv1'],
                        'group' => 'sponsors',
                        'slides' => [100],
                    ],
                ];
            }
            if ($name === 'teksttv_duration_image') {
                return 7;
            }
            return $default;
        });
        Functions\expect('wp_get_attachment_url')->andReturn('https://example.com/img.jpg');

        $block = ['groups' => ['sponsors']];
        $result = CampaignLoopBlock::build($block, 'tv1');

        $this->assertSame(7000, $result[0]['duration']);
    }
}
