<?php

namespace TekstTV\Tests\Unit\Blocks\Common;

use Brain\Monkey\Functions;
use TekstTV\Blocks\Common\TaxonomyFilters;
use TekstTV\Helpers;
use TekstTV\Tests\Unit\TestCase;

class TaxonomyFiltersTest extends TestCase
{
    public function test_render_selects_resubmits_a_stored_filter_on_a_taxonomy_that_is_not_listed(): void
    {
        Functions\stubEscapeFunctions();
        Functions\when('get_option')->justReturn(['category', 'regio']);
        Functions\when('selected')->justReturn('');
        $cache = new \ReflectionProperty(Helpers::class, 'post_taxonomies_cache');
        $cache->setValue(null, [['name' => 'category', 'label' => 'Category', 'terms' => [1 => 'Nieuws']]]);

        ob_start();
        TaxonomyFilters::render_selects(7, ['category' => [1], 'regio' => [4, 5]], 'teksttv_ticker');
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('<input type="hidden" name="teksttv_ticker[7][taxonomy_filters][regio][]" value="4" />', $html);
        $this->assertStringContainsString('<input type="hidden" name="teksttv_ticker[7][taxonomy_filters][regio][]" value="5" />', $html);
        $this->assertStringNotContainsString('type="hidden" name="teksttv_ticker[7][taxonomy_filters][category]', $html);
    }

    public function test_render_selects_shows_enabled_taxonomies_and_switched_off_ones_with_a_stored_filter(): void
    {
        Functions\stubEscapeFunctions();
        Functions\when('get_option')->justReturn(['category']);
        $cache = new \ReflectionProperty(Helpers::class, 'post_taxonomies_cache');
        $cache->setValue(null, [
            ['name' => 'category', 'label' => 'Category', 'terms' => [1 => 'Nieuws']],
            ['name' => 'regio', 'label' => 'Regio', 'terms' => [4 => 'Roosendaal']],
            ['name' => 'post_tag', 'label' => 'Tag', 'terms' => [9 => 'Sport']],
        ]);

        ob_start();
        TaxonomyFilters::render_selects(7, ['regio' => [4]], 'teksttv_blocks');
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('name="teksttv_blocks[7][taxonomy_filters][category][]"', $html);
        $this->assertStringContainsString('name="teksttv_blocks[7][taxonomy_filters][regio][]"', $html);
        $this->assertStringContainsString('<option value="4" selected>Roosendaal</option>', $html);
        $this->assertStringNotContainsString('post_tag', $html);
    }
}
