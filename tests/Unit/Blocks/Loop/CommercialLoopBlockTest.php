<?php

namespace TekstTV\Tests\Unit\Blocks\Loop;

use Brain\Monkey\Functions;
use TekstTV\Blocks\Loop\CommercialLoopBlock;
use TekstTV\Tests\Unit\TestCase;

class CommercialLoopBlockTest extends TestCase
{
    public function test_save_with_commercial_blocks(): void
    {
        // Runtime lookup uses stable IDs.
        $result = CommercialLoopBlock::save([
            'commercial_block_ids' => ['cblock_aaa111', 'cblock_bbb222'],
            'intro_image_id' => '10',
            'outro_image_id' => '20',
        ]);

        $this->assertSame(['cblock_aaa111', 'cblock_bbb222'], $result['commercial_block_ids']);
        $this->assertSame(10, $result['intro_image_id']);
        $this->assertSame(20, $result['outro_image_id']);
    }

    public function test_save_filters_empty_commercial_blocks(): void
    {
        $result = CommercialLoopBlock::save([
            'commercial_block_ids' => ['cblock_aaa111', '', 'cblock_bbb222'],
        ]);

        $this->assertSame(['cblock_aaa111', 'cblock_bbb222'], $result['commercial_block_ids']);
    }

    public function test_save_with_limit(): void
    {
        $result = CommercialLoopBlock::save([
            'commercial_block_ids' => ['A'],
            'limit' => '5',
        ]);

        $this->assertSame(5, $result['limit']);
    }

    public function test_save_omits_empty_limit(): void
    {
        $result = CommercialLoopBlock::save([
            'commercial_block_ids' => ['A'],
            'limit' => '',
        ]);

        $this->assertArrayNotHasKey('limit', $result);
    }

    public function test_save_clamps_limit_to_ui_max(): void
    {
        $result = CommercialLoopBlock::save([
            'commercial_block_ids' => ['A'],
            'limit' => '9999',
        ]);

        $this->assertSame(100, $result['limit']);
    }

    public function test_save_empty_commercial_blocks_defaults(): void
    {
        $result = CommercialLoopBlock::save([]);

        $this->assertSame([], $result['commercial_block_ids']);
        $this->assertSame(0, $result['intro_image_id']);
        $this->assertSame(0, $result['outro_image_id']);
    }

    public function test_save_non_array_commercial_blocks(): void
    {
        $result = CommercialLoopBlock::save(['commercial_block_ids' => 'single']);

        $this->assertSame([], $result['commercial_block_ids']);
    }

    public function test_build_returns_empty_when_no_commercial_blocks(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = ['commercial_block_ids' => []];
        $this->assertSame([], CommercialLoopBlock::build($block, 'tv1'));
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
                    'commercial_block_id' => 'sponsors',
                    'date_start' => '2026-04-01',
                    'date_end' => '2026-04-30',
                    'duration' => 5,
                    'slides' => [100, 101],
                ],
            ]);
        Functions\expect('wp_get_attachment_url')
            ->andReturnUsing(fn ($id) => 'https://example.com/img-' . $id . '.jpg');

        $block = ['commercial_block_ids' => ['sponsors']];
        $result = CommercialLoopBlock::build($block, 'tv1');

        $this->assertCount(2, $result);
        $this->assertSame('commercial', $result[0]['type']);
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
                    'commercial_block_id' => 'sponsors',
                    'slides' => [100],
                ],
            ]);

        $block = ['commercial_block_ids' => ['sponsors']];
        $result = CommercialLoopBlock::build($block, 'tv1');

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
                    'commercial_block_id' => 'sponsors',
                    'slides' => [1, 2, 3, 4, 5],
                ],
            ]);
        Functions\expect('get_option')
            ->with('teksttv_duration_image', 7)
            ->andReturn(7);
        Functions\expect('wp_get_attachment_url')
            ->andReturnUsing(fn ($id) => 'https://example.com/img-' . $id . '.jpg');

        $block = ['commercial_block_ids' => ['sponsors'], 'limit' => 2];
        $result = CommercialLoopBlock::build($block, 'tv1');

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
                    'commercial_block_id' => 'sponsors',
                    'slides' => [100],
                    'duration' => 5,
                ],
            ]);
        Functions\expect('wp_get_attachment_url')
            ->andReturnUsing(fn ($id) => 'https://example.com/img-' . $id . '.jpg');

        $block = [
            'commercial_block_ids' => ['sponsors'],
            'intro_image_id' => 50,
            'outro_image_id' => 51,
        ];
        $result = CommercialLoopBlock::build($block, 'tv1');

        $this->assertCount(3, $result);
        $this->assertSame('commercial_transition', $result[0]['type']);
        $this->assertSame(5000, $result[0]['duration']);
        $this->assertSame('commercial', $result[1]['type']);
        $this->assertSame('commercial_transition', $result[2]['type']);
    }

    public function test_build_no_intro_outro_when_no_matching_campaigns(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([]);

        $block = [
            'commercial_block_ids' => ['sponsors'],
            'intro_image_id' => 50,
            'outro_image_id' => 51,
        ];
        $result = CommercialLoopBlock::build($block, 'tv1');

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
                        'commercial_block_id' => 'sponsors',
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

        $block = ['commercial_block_ids' => ['sponsors']];
        $result = CommercialLoopBlock::build($block, 'tv1');

        $this->assertSame(7000, $result[0]['duration']);
    }
}
