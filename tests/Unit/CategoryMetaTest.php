<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\CategoryMeta;

class CategoryMetaTest extends TestCase
{
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
        Functions\when('get_option')->justReturn([]);
        Functions\when('delete_transient')->justReturn(true);

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
        Functions\when('get_option')->justReturn([]);
        Functions\when('delete_transient')->justReturn(true);

        CategoryMeta::save_term_meta(10);
    }
}
