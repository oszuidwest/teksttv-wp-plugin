<?php

namespace TekstTV\Tests\Unit\Blocks\Ticker;

use TekstTV\Blocks\Ticker\TickerTextBlock;
use TekstTV\Tests\Unit\TestCase;

class TickerTextBlockTest extends TestCase
{
    public function test_save_with_message(): void
    {
        $result = TickerTextBlock::save(['message' => 'Breaking news']);
        $this->assertSame(['message' => 'Breaking news'], $result);
    }

    public function test_save_returns_null_for_empty(): void
    {
        $this->assertNull(TickerTextBlock::save(['message' => '']));
    }

    public function test_save_returns_null_for_missing(): void
    {
        $this->assertNull(TickerTextBlock::save([]));
    }

    public function test_build_returns_message(): void
    {
        $result = TickerTextBlock::build(['message' => 'Hello world'], 'tv1');
        $this->assertSame([['message' => 'Hello world']], $result);
    }

    public function test_build_returns_empty_for_no_message(): void
    {
        $this->assertSame([], TickerTextBlock::build(['message' => ''], 'tv1'));
        $this->assertSame([], TickerTextBlock::build([], 'tv1'));
    }

    public function test_build_returns_trimmed_message_passthrough(): void
    {
        $result = TickerTextBlock::build(['message' => '  spaced  '], 'tv1');
        $this->assertSame([['message' => '  spaced  ']], $result);
    }
}
