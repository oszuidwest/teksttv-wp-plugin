<?php

namespace TekstTV\Tests\Unit\Blocks\Ticker;

use TekstTV\Blocks\Ticker\TickerHeadlinesBlock;
use TekstTV\Tests\Unit\TestCase;

class TickerHeadlinesBlockTest extends TestCase
{
    public function test_save_defaults(): void
    {
        $result = TickerHeadlinesBlock::save([]);
        $this->assertSame(5, $result['count']);
        $this->assertArrayNotHasKey('prefix', $result);
    }

    public function test_save_clamps_count(): void
    {
        $result = TickerHeadlinesBlock::save(['count' => '99']);
        $this->assertSame(20, $result['count']);

        $result = TickerHeadlinesBlock::save(['count' => '0']);
        $this->assertSame(1, $result['count']);
    }

    public function test_save_with_prefix(): void
    {
        $result = TickerHeadlinesBlock::save(['prefix' => 'Nieuws:']);
        $this->assertSame('Nieuws:', $result['prefix']);
    }

    public function test_save_omits_empty_prefix(): void
    {
        $result = TickerHeadlinesBlock::save(['prefix' => '']);
        $this->assertArrayNotHasKey('prefix', $result);
    }

    public function test_save_with_taxonomy_filters(): void
    {
        $result = TickerHeadlinesBlock::save([
            'count' => '3',
            'taxonomy_filters' => ['category' => ['1']],
        ]);

        $this->assertArrayHasKey('taxonomy_filters', $result);
        $this->assertSame([1], $result['taxonomy_filters']['category']);
    }

    public function test_save_omits_empty_taxonomy_filters(): void
    {
        $result = TickerHeadlinesBlock::save([
            'taxonomy_filters' => ['category' => ['0']],
        ]);

        $this->assertArrayNotHasKey('taxonomy_filters', $result);
    }

}
