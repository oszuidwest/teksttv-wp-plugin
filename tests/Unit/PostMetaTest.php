<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\PostMeta;

class PostMetaTest extends TestCase
{
    /** @var list<array{0: int, 1: string, 2: mixed}> Captured update_post_meta calls */
    private array $metaUpdates = [];

    /** @var list<array{0: int, 1: string}> Captured delete_post_meta calls */
    private array $metaDeletes = [];

    /**
     * Set up WP function stubs for process_save().
     *
     * @param list<string> $features Enabled features.
     */
    private function setupProcessSave(array $features = []): void
    {
        $this->metaUpdates = [];
        $this->metaDeletes = [];

        Functions\when('update_post_meta')->alias(function (int $post_id, string $key, $value) {
            $this->metaUpdates[] = [$post_id, $key, $value];
            return true;
        });
        Functions\when('delete_post_meta')->alias(function (int $post_id, string $key) {
            $this->metaDeletes[] = [$post_id, $key];
            return true;
        });
        Functions\when('wp_kses')->alias(function ($content) {
            return $content;
        });
        Functions\when('get_option')->alias(function (string $name, $default = false) use ($features) {
            if ($name === 'teksttv_features') {
                return $features;
            }
            return $default;
        });
    }

    /**
     * Find a meta update by key.
     */
    private function findMetaUpdate(string $key): mixed
    {
        foreach ($this->metaUpdates as [$postId, $metaKey, $value]) {
            if ($metaKey === $key) {
                return $value;
            }
        }
        return null;
    }

    private function wasMetaUpdated(string $key): bool
    {
        foreach ($this->metaUpdates as [$postId, $metaKey, $value]) {
            if ($metaKey === $key) {
                return true;
            }
        }
        return false;
    }

    private function wasMetaDeleted(string $key): bool
    {
        foreach ($this->metaDeletes as [$postId, $metaKey]) {
            if ($metaKey === $key) {
                return true;
            }
        }
        return false;
    }

    // =========================================================================
    // Active toggle
    // =========================================================================

    public function test_process_save_saves_active(): void
    {
        $this->setupProcessSave();
        PostMeta::process_save(1, ['active' => true, 'content' => '']);

        $this->assertSame('1', $this->findMetaUpdate('_teksttv_active'));
    }

    public function test_process_save_saves_inactive(): void
    {
        $this->setupProcessSave();
        PostMeta::process_save(1, ['active' => false, 'content' => '']);

        $this->assertSame('0', $this->findMetaUpdate('_teksttv_active'));
    }

    // =========================================================================
    // Feature: custom_title
    // =========================================================================

    public function test_process_save_skips_title_when_feature_disabled(): void
    {
        $this->setupProcessSave([]);
        PostMeta::process_save(1, ['active' => true, 'title' => 'Custom', 'content' => '']);

        $this->assertFalse($this->wasMetaUpdated('_teksttv_title'));
    }

    public function test_process_save_saves_title_when_feature_enabled(): void
    {
        $this->setupProcessSave(['custom_title']);
        PostMeta::process_save(1, ['active' => true, 'title' => 'Custom', 'content' => '']);

        $this->assertSame('Custom', $this->findMetaUpdate('_teksttv_title'));
    }

    // =========================================================================
    // Feature: scheduling
    // =========================================================================

    public function test_process_save_skips_scheduling_when_feature_disabled(): void
    {
        $this->setupProcessSave([]);
        PostMeta::process_save(1, [
            'active' => true,
            'content' => '',
            'date_start' => '2026-04-01',
            'days' => ['1', '3'],
        ]);

        $this->assertFalse($this->wasMetaUpdated('_teksttv_date_start'));
        $this->assertFalse($this->wasMetaUpdated('_teksttv_days'));
    }

    public function test_process_save_saves_scheduling_when_enabled(): void
    {
        $this->setupProcessSave(['scheduling']);
        PostMeta::process_save(1, [
            'active' => true,
            'content' => '',
            'date_start' => '2026-04-01',
            'date_end' => '2026-04-30',
            'days' => ['1', '3', '5'],
        ]);

        $this->assertSame('2026-04-01', $this->findMetaUpdate('_teksttv_date_start'));
        $this->assertSame('2026-04-30', $this->findMetaUpdate('_teksttv_date_end'));
        $this->assertSame(['1', '3', '5'], $this->findMetaUpdate('_teksttv_days'));
    }

    public function test_process_save_filters_invalid_days(): void
    {
        $this->setupProcessSave(['scheduling']);
        PostMeta::process_save(1, [
            'active' => true,
            'content' => '',
            'days' => ['1', '8', 'abc', '5', '-1', '0'],
        ]);

        $this->assertSame(['1', '5'], $this->findMetaUpdate('_teksttv_days'));
    }

    // =========================================================================
    // Feature: extra_images
    // =========================================================================

    public function test_process_save_skips_images_when_feature_disabled(): void
    {
        $this->setupProcessSave([]);
        PostMeta::process_save(1, ['active' => true, 'content' => '', 'images' => [10]]);

        $this->assertFalse($this->wasMetaUpdated('_teksttv_images'));
    }

    public function test_process_save_saves_images_when_enabled(): void
    {
        $this->setupProcessSave(['extra_images']);
        PostMeta::process_save(1, ['active' => true, 'content' => '', 'images' => [10, 20]]);

        $this->assertSame([10, 20], $this->findMetaUpdate('_teksttv_images'));
    }

    public function test_process_save_filters_zero_image_ids(): void
    {
        $this->setupProcessSave(['extra_images']);
        PostMeta::process_save(1, ['active' => true, 'content' => '', 'images' => [0, 10, 0]]);

        $images = $this->findMetaUpdate('_teksttv_images');
        $this->assertSame([10], array_values($images));
    }

    // =========================================================================
    // Feature: sidebar_image — three states
    // =========================================================================

    public function test_process_save_sidebar_custom_id(): void
    {
        $this->setupProcessSave(['sidebar_image']);
        PostMeta::process_save(1, ['active' => true, 'content' => '', 'sidebar_image' => '42']);

        $this->assertSame(42, $this->findMetaUpdate('_teksttv_sidebar_image'));
    }

    public function test_process_save_sidebar_explicit_none(): void
    {
        $this->setupProcessSave(['sidebar_image']);
        PostMeta::process_save(1, ['active' => true, 'content' => '', 'sidebar_image' => '0']);

        $this->assertSame('0', $this->findMetaUpdate('_teksttv_sidebar_image'));
    }

    public function test_process_save_sidebar_default_deletes_meta(): void
    {
        $this->setupProcessSave(['sidebar_image']);
        PostMeta::process_save(1, ['active' => true, 'content' => '', 'sidebar_image' => '']);

        $this->assertTrue($this->wasMetaDeleted('_teksttv_sidebar_image'));
        $this->assertFalse($this->wasMetaUpdated('_teksttv_sidebar_image'));
    }

    public function test_process_save_skips_sidebar_when_feature_disabled(): void
    {
        $this->setupProcessSave([]);
        PostMeta::process_save(1, ['active' => true, 'content' => '', 'sidebar_image' => '42']);

        $this->assertFalse($this->wasMetaUpdated('_teksttv_sidebar_image'));
        $this->assertFalse($this->wasMetaDeleted('_teksttv_sidebar_image'));
    }

    // =========================================================================
    // Guard clauses (save_meta level, still using $_POST)
    // =========================================================================

    public function test_save_meta_returns_early_without_nonce(): void
    {
        $_POST = [];
        $post = \Mockery::mock(\WP_Post::class);
        $post->post_type = 'post';

        PostMeta::save_meta(1, $post);

        $this->assertEmpty($this->metaUpdates);
    }

    public function test_save_meta_returns_early_for_wrong_post_type(): void
    {
        $_POST = ['teksttv_meta_nonce' => 'valid'];
        $post = \Mockery::mock(\WP_Post::class);
        $post->post_type = 'page';

        Functions\when('wp_verify_nonce')->justReturn(true);
        Functions\when('wp_unslash')->alias(fn($v) => $v);

        PostMeta::save_meta(1, $post);

        $this->assertEmpty($this->metaUpdates);
    }

    public function test_save_meta_invalidates_slides_cache_after_meta_updates(): void
    {
        $_POST = [
            'teksttv_meta_nonce' => 'valid',
            'teksttv_active' => '1',
        ];
        $post = \Mockery::mock(\WP_Post::class);
        $post->post_type = 'post';

        Functions\when('wp_verify_nonce')->justReturn(true);
        Functions\when('wp_unslash')->alias(fn ($v) => $v);
        Functions\when('sanitize_text_field')->alias(fn ($v) => $v);
        Functions\when('absint')->alias(fn ($v) => abs((int) $v));
        Functions\when('current_user_can')->justReturn(true);
        $this->setupProcessSave();

        // Regression: save_post_post invalidates BEFORE this callback writes
        // the meta; a concurrent /slides request in that window can re-cache
        // stale data, so save_meta() must invalidate again after writing.
        Functions\expect('delete_transient')->once()->with('teksttv_slides_tv1');

        PostMeta::save_meta(1, $post);

        $this->assertNotEmpty($this->metaUpdates);
    }

    // =========================================================================
    // Broader slides-cache invalidation on editorial changes
    // =========================================================================

    public function test_invalidate_on_terms_change_clears_cache_for_post(): void
    {
        Functions\expect('get_post_type')->with(10)->andReturn('post');
        Functions\when('get_option')->justReturn([['slug' => 'tv1', 'label' => 'TV 1']]);
        Functions\expect('delete_transient')->once()->with('teksttv_slides_tv1');

        PostMeta::invalidate_on_terms_change(10);

        $this->assertTrue(true);
    }

    public function test_invalidate_on_terms_change_skips_non_post(): void
    {
        Functions\expect('get_post_type')->with(20)->andReturn('page');
        Functions\expect('delete_transient')->never();

        PostMeta::invalidate_on_terms_change(20);

        $this->assertTrue(true);
    }

    public function test_invalidate_on_post_save_skips_autosave(): void
    {
        Functions\expect('wp_is_post_autosave')->with(5)->andReturn(true);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\expect('delete_transient')->never();

        $post = \Mockery::mock(\WP_Post::class);
        PostMeta::invalidate_on_post_save(5, $post);

        $this->assertTrue(true);
    }

    public function test_invalidate_on_status_transition_clears_cache_on_publish(): void
    {
        $post = \Mockery::mock(\WP_Post::class);
        $post->post_type = 'post';
        Functions\when('get_option')->justReturn([['slug' => 'tv1', 'label' => 'TV 1']]);
        Functions\expect('delete_transient')->once()->with('teksttv_slides_tv1');

        PostMeta::invalidate_on_status_transition('publish', 'future', $post);

        $this->assertTrue(true);
    }

    public function test_invalidate_on_status_transition_skips_unchanged_and_non_publish(): void
    {
        $post = \Mockery::mock(\WP_Post::class);
        $post->post_type = 'post';
        Functions\expect('delete_transient')->never();

        PostMeta::invalidate_on_status_transition('draft', 'pending', $post);

        $this->assertTrue(true);
    }
}
