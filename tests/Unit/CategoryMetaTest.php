<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\CategoryMeta;

class CategoryMetaTest extends TestCase
{
    // =========================================================================
    // get_image_url()
    // =========================================================================

    public function test_get_image_url_returns_url_when_image_exists(): void
    {
        Functions\expect('get_term_meta')
            ->with(10, '_teksttv_category_image', true)
            ->andReturn('42');
        Functions\expect('wp_get_attachment_image_url')
            ->with(42, 'large')
            ->andReturn('https://example.com/image.jpg');

        $result = CategoryMeta::get_image_url(10);

        $this->assertSame('https://example.com/image.jpg', $result);
    }

    public function test_get_image_url_returns_null_when_no_meta(): void
    {
        Functions\expect('get_term_meta')
            ->with(10, '_teksttv_category_image', true)
            ->andReturn('');

        $result = CategoryMeta::get_image_url(10);

        $this->assertNull($result);
    }

    public function test_get_image_url_returns_null_when_attachment_missing(): void
    {
        Functions\expect('get_term_meta')
            ->with(10, '_teksttv_category_image', true)
            ->andReturn('99');
        Functions\expect('wp_get_attachment_image_url')
            ->with(99, 'large')
            ->andReturn(false);

        $result = CategoryMeta::get_image_url(10);

        $this->assertNull($result);
    }

    // =========================================================================
    // save_term_meta()
    // =========================================================================

    public function test_save_term_meta_returns_early_without_nonce(): void
    {
        $_POST = [];

        // Should not call update_term_meta or delete_term_meta
        Functions\expect('update_term_meta')->never();
        Functions\expect('delete_term_meta')->never();

        CategoryMeta::save_term_meta(10);
    }

    public function test_save_term_meta_returns_early_on_invalid_nonce(): void
    {
        $_POST = ['teksttv_category_nonce' => 'invalid'];

        Functions\expect('wp_verify_nonce')->andReturn(false);
        Functions\when('wp_unslash')->alias(fn($v) => $v);
        Functions\expect('update_term_meta')->never();
        Functions\expect('delete_term_meta')->never();

        CategoryMeta::save_term_meta(10);
    }

    public function test_save_term_meta_returns_early_without_capability(): void
    {
        $_POST = ['teksttv_category_nonce' => 'valid'];

        Functions\expect('wp_verify_nonce')->andReturn(true);
        Functions\when('wp_unslash')->alias(fn($v) => $v);
        Functions\expect('current_user_can')->with('manage_categories')->andReturn(false);
        Functions\expect('update_term_meta')->never();
        Functions\expect('delete_term_meta')->never();

        CategoryMeta::save_term_meta(10);
    }

    public function test_save_term_meta_updates_with_valid_image_id(): void
    {
        $_POST = [
            'teksttv_category_nonce' => 'valid',
            'teksttv_category_image' => '42',
        ];

        Functions\expect('wp_verify_nonce')->andReturn(true);
        Functions\when('wp_unslash')->alias(fn($v) => $v);
        Functions\expect('current_user_can')->with('manage_categories')->andReturn(true);
        Functions\expect('update_term_meta')
            ->once()
            ->with(10, '_teksttv_category_image', 42);

        CategoryMeta::save_term_meta(10);
    }

    public function test_save_term_meta_deletes_when_image_id_zero(): void
    {
        $_POST = [
            'teksttv_category_nonce' => 'valid',
            'teksttv_category_image' => '0',
        ];

        Functions\expect('wp_verify_nonce')->andReturn(true);
        Functions\when('wp_unslash')->alias(fn($v) => $v);
        Functions\expect('current_user_can')->with('manage_categories')->andReturn(true);
        Functions\expect('delete_term_meta')
            ->once()
            ->with(10, '_teksttv_category_image');

        CategoryMeta::save_term_meta(10);
    }
}
