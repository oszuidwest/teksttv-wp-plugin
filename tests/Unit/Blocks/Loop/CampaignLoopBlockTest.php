<?php

namespace TekstTV\Tests\Unit\Blocks\Loop;

use Brain\Monkey\Functions;
use TekstTV\Blocks\Loop\CampaignLoopBlock;
use TekstTV\Tests\Unit\TestCase;

class CampaignLoopBlockTest extends TestCase
{
    public function test_save_with_groups(): void
    {
        $result = CampaignLoopBlock::save([
            'groups' => ['Sponsors', 'Partners'],
            'intro_image_id' => '10',
            'outro_image_id' => '20',
        ]);

        $this->assertSame(['Sponsors', 'Partners'], $result['groups']);
        $this->assertSame(10, $result['intro_image_id']);
        $this->assertSame(20, $result['outro_image_id']);
    }

    public function test_save_filters_empty_groups(): void
    {
        $result = CampaignLoopBlock::save([
            'groups' => ['Sponsors', '', 'Partners'],
        ]);

        $this->assertSame(['Sponsors', 'Partners'], $result['groups']);
    }

    public function test_save_with_limit(): void
    {
        $result = CampaignLoopBlock::save([
            'groups' => ['A'],
            'limit' => '5',
        ]);

        $this->assertSame(5, $result['limit']);
    }

    public function test_save_omits_empty_limit(): void
    {
        $result = CampaignLoopBlock::save([
            'groups' => ['A'],
            'limit' => '',
        ]);

        $this->assertArrayNotHasKey('limit', $result);
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
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = ['groups' => []];
        $this->assertSame([], CampaignLoopBlock::build($block, 'tv1'));
    }

    public function test_build_returns_empty_when_not_scheduled(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07 12:00:00'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = [
            'groups' => ['sponsors'],
            'date_start' => '2026-05-01',
        ];
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
            ]);
        Functions\expect('wp_get_attachment_url')
            ->andReturnUsing(fn ($id) => 'https://example.com/img-' . $id . '.jpg');

        $block = ['groups' => ['sponsors']];
        $result = CampaignLoopBlock::build($block, 'tv1');

        $this->assertCount(2, $result);
        $this->assertSame('campaign', $result[0]['type']);
        $this->assertSame(5000, $result[0]['duration']);
        $this->assertSame('https://example.com/img-100.jpg', $result[0]['url']);
        $this->assertSame('https://example.com/img-101.jpg', $result[1]['url']);
    }

    public function test_build_filters_by_channel(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
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

    public function test_build_rotation_limit(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([
                [
                    'channels' => ['tv1'],
                    'group' => 'sponsors',
                    'slides' => [1, 2, 3, 4, 5],
                ],
            ]);
        Functions\expect('get_option')
            ->with('teksttv_duration_image', 7)
            ->andReturn(7);
        Functions\expect('wp_get_attachment_url')
            ->andReturnUsing(fn ($id) => 'https://example.com/img-' . $id . '.jpg');

        $block = ['groups' => ['sponsors'], 'limit' => 2];
        $result = CampaignLoopBlock::build($block, 'tv1');

        $this->assertCount(2, $result);
    }

    public function test_build_intro_outro(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
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
        $this->assertSame('campaign_transition', $result[0]['type']);
        $this->assertSame(5000, $result[0]['duration']);
        $this->assertSame('campaign', $result[1]['type']);
        $this->assertSame('campaign_transition', $result[2]['type']);
    }

    public function test_build_no_intro_outro_when_no_matching_campaigns(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
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
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
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
