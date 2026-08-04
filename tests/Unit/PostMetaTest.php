<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use TekstTV\Helpers;
use TekstTV\PostMeta;

class PostMetaTest extends TestCase
{
    public function test_register_tinymce_plugin_adds_separator(): void
    {
        $plugins = ['existing' => 'https://example.com/existing.js'];

        $this->assertSame([
            'existing' => 'https://example.com/existing.js',
            'teksttv_separator' => TEKSTTV_PLUGIN_URL . 'assets/tinymce-separator.js',
        ], PostMeta::register_tinymce_plugin($plugins));
    }

    public function test_enqueue_assets_skips_unrelated_admin_hook(): void
    {
        Functions\expect('current_user_can')->never();
        Functions\expect('get_current_screen')->never();
        $this->expectNoAssets();

        PostMeta::enqueue_assets('edit.php');

        $this->assertFalse(Filters\has('mce_external_plugins'));
    }

    public function test_enqueue_assets_skips_user_without_teksttv_capability(): void
    {
        Functions\expect('current_user_can')->with('edit_teksttv')->once()->andReturn(false);
        Functions\expect('get_current_screen')->never();
        $this->expectNoAssets();

        PostMeta::enqueue_assets('post.php');

        $this->assertFalse(Filters\has('mce_external_plugins'));
    }

    public function test_enqueue_assets_skips_missing_screen(): void
    {
        Functions\expect('current_user_can')->with('edit_teksttv')->once()->andReturn(true);
        Functions\when('get_current_screen')->justReturn(null);
        $this->expectNoAssets();

        PostMeta::enqueue_assets('post.php');

        $this->assertFalse(Filters\has('mce_external_plugins'));
    }

    public function test_enqueue_assets_skips_non_post_screen(): void
    {
        Functions\expect('current_user_can')->with('edit_teksttv')->once()->andReturn(true);
        Functions\when('get_current_screen')->justReturn((object) ['post_type' => 'page']);
        $this->expectNoAssets();

        PostMeta::enqueue_assets('post.php');

        $this->assertFalse(Filters\has('mce_external_plugins'));
    }

    /**
     * @return list<array{string}>
     */
    public static function supportedHooks(): array
    {
        return [['post.php'], ['post-new.php']];
    }

    #[DataProvider('supportedHooks')]
    public function test_enqueue_assets_loads_on_supported_post_screen_for_authorized_user(string $hook): void
    {
        Filters\expectAdded('mce_external_plugins')
            ->with([PostMeta::class, 'register_tinymce_plugin'])
            ->once();
        $this->stubSuccessfulAssetEnqueue(['page_separator', 'bold']);

        PostMeta::enqueue_assets($hook);
    }

    public function test_enqueue_assets_skips_separator_plugin_when_feature_disabled(): void
    {
        $this->stubSuccessfulAssetEnqueue(['bold']);

        PostMeta::enqueue_assets('post.php');

        $this->assertFalse(Filters\has('mce_external_plugins'));
    }

    public function test_enqueue_assets_skips_separator_plugin_for_plain_text_editor(): void
    {
        $this->stubSuccessfulAssetEnqueue(['page_separator']);

        PostMeta::enqueue_assets('post.php');

        $this->assertFalse(Filters\has('mce_external_plugins'));
    }

    public function test_enqueue_assets_omits_the_auto_draft_fallback_title(): void
    {
        $this->stubSuccessfulAssetEnqueue(['bold'], true);

        PostMeta::enqueue_assets('post-new.php');
    }

    /**
     * @param list<string> $features
     */
    private function stubSuccessfulAssetEnqueue(array $features, bool $auto_draft = false): void
    {
        $page_separator = in_array('page_separator', $features, true);
        Functions\expect('current_user_can')->with('edit_teksttv')->once()->andReturn(true);
        Functions\when('get_current_screen')->justReturn((object) ['post_type' => 'post']);
        Functions\expect('wp_enqueue_media')->once();
        Functions\expect('wp_enqueue_script')->with(
            'teksttv-admin',
            TEKSTTV_PLUGIN_URL . 'assets/admin.js',
            Helpers::admin_script_dependencies(),
            TEKSTTV_VERSION,
            true
        )->once();
        Functions\expect('wp_enqueue_style')->with(
            'teksttv-admin',
            TEKSTTV_PLUGIN_URL . 'assets/admin.css',
            [],
            TEKSTTV_VERSION
        )->once();
        Functions\when('wp_script_is')->justReturn(false);
        Functions\when('get_the_ID')->justReturn($auto_draft ? 42 : false);
        if ($auto_draft) {
            $post = self::makePost();
            $post->post_date = '0000-00-00 00:00:00';
            Functions\when('get_post_thumbnail_id')->justReturn(0);
            Functions\when('get_post_meta')->justReturn('');
            Functions\when('get_post')->justReturn($post);
            Functions\when('current_time')->justReturn('2026-08-04');
            Functions\expect('get_post_status')->with(42)->once()->andReturn('auto-draft');
            Functions\expect('get_the_title')->never();
        }
        Functions\when('get_option')->alias(static function (string $name, mixed $default = false) use ($features): mixed {
            return $name === 'teksttv_features' ? $features : $default;
        });
        Functions\when('rest_url')->returnArg();
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\expect('wp_add_inline_script')->with(
            'teksttv-admin',
            Mockery::on(static function (string $script) use ($page_separator, $auto_draft): bool {
                $prefix = 'var teksttvPost = ';
                if (!str_starts_with($script, $prefix) || !str_ends_with($script, ';')) {
                    return false;
                }

                $config = json_decode(substr($script, strlen($prefix), -1), true);
                return is_array($config)
                    && $config['pageSeparator'] === $page_separator
                    && (!$auto_draft || ($config['isNewPost'] === true && $config['fallbackTitle'] === ''));
            }),
            'before'
        )->once();
    }

    private function expectNoAssets(): void
    {
        Functions\expect('wp_enqueue_media')->never();
        Functions\expect('wp_enqueue_script')->never();
        Functions\expect('wp_enqueue_style')->never();
        Functions\expect('wp_add_inline_script')->never();
    }
}
