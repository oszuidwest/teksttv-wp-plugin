<?php

namespace TekstTV\Tests\Unit;

use TekstTV\BlockRegistry;

class BlockRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset the static $types array before each test
        $ref = new \ReflectionProperty(BlockRegistry::class, 'types');
        $ref->setValue(null, []);
    }

    private function registerTestBlock(string $slug = 'test_block', string $context = 'loop'): void
    {
        BlockRegistry::register($slug, [
            'label' => 'Test Block',
            'icon' => 'admin-post',
            'color' => '#000',
            'context' => $context,
            'render' => function () {
            },
            'save' => function ($raw) {
                return ['name' => $raw['name'] ?? ''];
            },
            'build' => function ($data) {
                return [['type' => 'test', 'data' => $data]];
            },
        ]);
    }

    // =========================================================================
    // register() + get()
    // =========================================================================

    public function test_register_and_get(): void
    {
        $this->registerTestBlock();
        $type = BlockRegistry::get('test_block');

        $this->assertNotNull($type);
        $this->assertSame('Test Block', $type['label']);
        $this->assertSame('loop', $type['context']);
    }

    public function test_get_returns_null_for_unregistered(): void
    {
        $this->assertNull(BlockRegistry::get('nonexistent'));
    }

    // =========================================================================
    // all()
    // =========================================================================

    public function test_all_returns_all_types(): void
    {
        $this->registerTestBlock('loop_block', 'loop');
        $this->registerTestBlock('ticker_block', 'ticker');

        $all = BlockRegistry::all();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('loop_block', $all);
        $this->assertArrayHasKey('ticker_block', $all);
    }

    public function test_all_filters_by_context_loop(): void
    {
        $this->registerTestBlock('loop_block', 'loop');
        $this->registerTestBlock('ticker_block', 'ticker');
        $this->registerTestBlock('both_block', 'both');

        $loop = BlockRegistry::all('loop');
        $this->assertCount(2, $loop); // loop_block + both_block
        $this->assertArrayHasKey('loop_block', $loop);
        $this->assertArrayHasKey('both_block', $loop);
        $this->assertArrayNotHasKey('ticker_block', $loop);
    }

    public function test_all_filters_by_context_ticker(): void
    {
        $this->registerTestBlock('loop_block', 'loop');
        $this->registerTestBlock('ticker_block', 'ticker');
        $this->registerTestBlock('both_block', 'both');

        $ticker = BlockRegistry::all('ticker');
        $this->assertCount(2, $ticker); // ticker_block + both_block
        $this->assertArrayHasKey('ticker_block', $ticker);
        $this->assertArrayHasKey('both_block', $ticker);
    }

    // =========================================================================
    // save()
    // =========================================================================

    public function test_save_returns_sanitized_data_with_type(): void
    {
        $this->registerTestBlock();
        $result = BlockRegistry::save('test_block', ['name' => 'My Block']);

        $this->assertIsArray($result);
        $this->assertSame('test_block', $result['type']);
        $this->assertSame('My Block', $result['name']);
    }

    public function test_save_returns_null_for_unknown_type(): void
    {
        $this->assertNull(BlockRegistry::save('nonexistent', ['foo' => 'bar']));
    }

    public function test_save_adds_type_key_automatically(): void
    {
        $this->registerTestBlock();
        $result = BlockRegistry::save('test_block', []);

        $this->assertSame('test_block', $result['type']);
    }

    // =========================================================================
    // build()
    // =========================================================================

    public function test_build_returns_slides_array(): void
    {
        $this->registerTestBlock();
        $result = BlockRegistry::build('test_block', ['key' => 'value'], 'tv1');

        $this->assertCount(1, $result);
        $this->assertSame('test', $result[0]['type']);
    }

    public function test_build_returns_empty_for_unknown_type(): void
    {
        $this->assertSame([], BlockRegistry::build('nonexistent', [], 'tv1'));
    }
}
