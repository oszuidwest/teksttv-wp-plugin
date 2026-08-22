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

    public function test_plain_text_content_survives_repeated_save_and_render_cycles(): void
    {
        $stored_meta = [
            '_teksttv_content' => '<p>&lt;strong&gt;literal&lt;/strong&gt; &amp;lt;em&amp;gt;</p><p>Tweede regel</p>',
        ];
        $expected_editor_content = "<strong>literal</strong> &lt;em&gt;\nTweede regel";
        $expected_storage = "&lt;strong&gt;literal&lt;/strong&gt; &amp;lt;em&amp;gt;\nTweede regel";
        Functions\when('get_option')->justReturn([]);
        Functions\expect('wp_kses')->twice()->with($expected_storage, ['p' => [], 'br' => []])
            ->andReturn($expected_storage);
        Functions\when('update_post_meta')->alias(
            static function (int $post_id, string $key, mixed $value) use (&$stored_meta): bool {
                $stored_meta[$key] = $value;
                return true;
            }
        );

        for ($cycle = 0; $cycle < 2; $cycle++) {
            $editor_content = self::callPrivate(
                PostMeta::class,
                'plain_editor_content',
                [$stored_meta['_teksttv_content']]
            );
            $this->assertSame($expected_editor_content, $editor_content);

            self::callPrivate(PostMeta::class, 'process_save', [
                42,
                ['active' => true, 'content' => $editor_content],
            ]);
            $this->assertSame($expected_storage, $stored_meta['_teksttv_content']);
        }
    }

    public function test_prepare_editor_content_removes_markup_when_rich_text_is_disabled(): void
    {
        Functions\when('get_option')->justReturn(['ai_generate']);

        $this->assertSame(
            "Eerste alinea\nTweede\nregel",
            PostMeta::prepare_editor_content('<p>Eerste alinea</p><p>Tweede<br>regel</p>')
        );
    }

    public function test_prepare_editor_content_keeps_markup_for_tinymce(): void
    {
        Functions\when('get_option')->justReturn(['bold']);
        $content = '<p><strong>Opgemaakte tekst</strong></p>';

        $this->assertSame($content, PostMeta::prepare_editor_content($content));
    }

    public function test_prepare_editor_content_removes_markup_when_user_disabled_visual_editor(): void
    {
        Functions\when('get_option')->justReturn(['bold']);
        Functions\when('user_can_richedit')->justReturn(false);

        $this->assertSame(
            "Eerste alinea\nTweede regel",
            PostMeta::prepare_editor_content('<p>Eerste alinea</p><p>Tweede regel</p>')
        );
    }

    public function test_rich_text_content_still_uses_the_feature_allowlist(): void
    {
        $features = ['bold'];
        $stored_content = null;
        Functions\when('get_option')->alias(
            static fn(string $name, mixed $default = false): mixed => $name === 'teksttv_features' ? $features : $default
        );
        Functions\expect('wp_kses')->once()->with(
            '<p><strong>Vet</strong><em>niet cursief</em></p>',
            ['p' => [], 'br' => [], 'strong' => [], 'b' => []]
        )->andReturn('<p><strong>Vet</strong>niet cursief</p>');
        Functions\when('update_post_meta')->alias(
            static function (int $post_id, string $key, mixed $value) use (&$stored_content): bool {
                if ($key === '_teksttv_content') {
                    $stored_content = $value;
                }
                return true;
            }
        );

        self::callPrivate(PostMeta::class, 'process_save', [
            42,
            ['active' => true, 'content' => '<p><strong>Vet</strong><em>niet cursief</em></p>'],
        ]);

        $this->assertSame('<p><strong>Vet</strong>niet cursief</p>', $stored_content);
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
