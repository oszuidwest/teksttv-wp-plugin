<?php

namespace TekstTV\Tests\Unit\Blocks\Loop;

use Brain\Monkey\Functions;
use TekstTV\BlockRegistry;
use TekstTV\Blocks\Loop\MessagesLoopBlock;
use TekstTV\Messages;
use TekstTV\Tests\Unit\TestCase;

class MessagesLoopBlockTest extends TestCase
{
    public function test_block_is_registered_only_while_the_feature_is_on(): void
    {
        Functions\when('get_option')->justReturn([]);
        MessagesLoopBlock::register();
        $this->assertNull(BlockRegistry::get('messages'));

        Functions\when('get_option')->justReturn([Messages::FEATURE]);
        MessagesLoopBlock::register();
        $this->assertSame('loop', BlockRegistry::get('messages')['context']);
    }

    public function test_save_defaults(): void
    {
        $this->assertSame(['count' => 5], MessagesLoopBlock::save([]));
    }

    public function test_save_clamps_count_and_durations(): void
    {
        $this->assertSame(
            ['count' => 50, 'duration_text' => 120, 'duration_image' => 1],
            MessagesLoopBlock::save(['count' => '999', 'duration_text' => '999', 'duration_image' => '0'])
        );
    }
}
