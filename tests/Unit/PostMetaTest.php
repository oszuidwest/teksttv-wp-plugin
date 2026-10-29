<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use TekstTV\Helpers;
use TekstTV\Messages;
use TekstTV\PostMeta;

class PostMetaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Default options: stored features and settings fall back to their defaults.
        Functions\when('get_option')->alias(static fn(string $name, mixed $default = false): mixed => $default);
    }

    /**
     * @param list<string> $features
     */
    private static function stubOptions(array $features): void
    {
        Functions\when('get_option')->alias(static fn(string $name, mixed $default = false): mixed => match ($name) {
            'teksttv_features' => $features,
            'teksttv_channels' => [['slug' => 'tv1', 'label' => 'TV 1'], ['slug' => 'tv2', 'label' => 'TV 2']],
            default => $default,
        });
    }

    public function test_register_tinymce_plugin_adds_separator(): void
    {
        $plugins = ['existing' => 'https://example.com/existing.js'];

        $this->assertSame([
            'existing' => 'https://example.com/existing.js',
            'teksttv_separator' => TEKSTTV_PLUGIN_URL . 'assets/tinymce-separator.js',
        ], PostMeta::register_tinymce_plugin($plugins));
    }

    public function test_user_can_edit_type_requires_the_mapped_capability(): void
    {
        Functions\expect('current_user_can')->with('edit_teksttv')->twice()->andReturn(true, false);

        $this->assertTrue(PostMeta::user_can_edit_type('post'));
        $this->assertFalse(PostMeta::user_can_edit_type('post'));
    }

    public function test_user_can_edit_type_rejects_unsupported_post_types(): void
    {
        Functions\expect('current_user_can')->never();

        $this->assertFalse(PostMeta::user_can_edit_type('page'));
    }

    public function test_register_meta_box_adds_box_for_permitted_post_types(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('add_meta_box')
            ->once()
            ->with('teksttv_meta', 'Tekst TV', [PostMeta::class, 'render_meta_box'], 'post', 'normal', 'high');

        PostMeta::register_meta_box();
    }

    public function test_register_meta_box_skips_user_without_capability(): void
    {
        Functions\when('current_user_can')->justReturn(false);
        Functions\expect('add_meta_box')->never();

        PostMeta::register_meta_box();
    }

    public function test_message_type_is_supported_only_while_the_feature_is_on(): void
    {
        $this->assertArrayNotHasKey(Messages::POST_TYPE, PostMeta::post_types());

        self::stubOptions([Messages::FEATURE]);

        $this->assertSame('edit_teksttv_messages', PostMeta::post_types()[Messages::POST_TYPE]);
    }

    public function test_message_save_forces_on_air_and_stores_channels(): void
    {
        $stored = $this->saveWithPost(Messages::POST_TYPE, ['teksttv_channels_shown' => '1', 'teksttv_channels' => ['tv2', 'tv9']]);

        $this->assertSame('1', $stored['_teksttv_active']);
        $this->assertSame(['tv2'], $stored[Messages::CHANNELS_META]);
        $this->assertSame('', $stored['_teksttv_title']);
    }

    public function test_message_save_without_channels_stores_all_channels(): void
    {
        $stored = $this->saveWithPost(Messages::POST_TYPE, ['teksttv_channels_shown' => '1']);

        $this->assertSame([], $stored[Messages::CHANNELS_META]);
    }

    public function test_message_save_keeps_channels_when_the_section_was_hidden(): void
    {
        $stored = $this->saveWithPost(Messages::POST_TYPE, []);

        $this->assertSame('1', $stored['_teksttv_active']);
        $this->assertArrayNotHasKey(Messages::CHANNELS_META, $stored);
    }

    public function test_article_save_keeps_the_toggle_and_ignores_channels(): void
    {
        $stored = $this->saveWithPost('post', ['teksttv_channels' => ['tv2'], 'teksttv_title' => 'Eigen kop']);

        $this->assertSame('0', $stored['_teksttv_active']);
        $this->assertSame('Eigen kop', $stored['_teksttv_title']);
        $this->assertArrayNotHasKey(Messages::CHANNELS_META, $stored);
    }

    public function test_message_save_requires_the_message_capability(): void
    {
        self::stubOptions([Messages::FEATURE]);
        Functions\when('wp_verify_nonce')->justReturn(true);
        Functions\when('current_user_can')->alias(static fn(string $cap): bool => $cap === 'edit_teksttv');
        Functions\expect('update_post_meta')->never();
        $_POST = ['teksttv_meta_nonce' => 'nonce', 'teksttv_content' => 'Tekst'];
        $post = self::makePost();
        $post->post_type = Messages::POST_TYPE;

        try {
            PostMeta::save_meta($post->ID, $post);
        } finally {
            $_POST = [];
        }
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function saveWithPost(string $post_type, array $fields): array
    {
        self::stubOptions([Messages::FEATURE, 'custom_title']);
        Functions\when('wp_verify_nonce')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_kses')->returnArg();
        $stored = [];
        Functions\when('update_post_meta')->alias(static function (int $post_id, string $key, mixed $value) use (&$stored): bool {
            $stored[$key] = $value;
            return true;
        });
        $_POST = ['teksttv_meta_nonce' => 'nonce', 'teksttv_content' => 'Tekst'] + $fields;
        $post = self::makePost();
        $post->post_type = $post_type;

        try {
            PostMeta::save_meta($post->ID, $post);
        } finally {
            $_POST = [];
        }

        return $stored;
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
        Functions\when('get_current_screen')->justReturn((object) ['post_type' => 'post']);
        Functions\expect('current_user_can')->with('edit_teksttv')->once()->andReturn(false);
        $this->expectNoAssets();

        PostMeta::enqueue_assets('post.php');

        $this->assertFalse(Filters\has('mce_external_plugins'));
    }

    public function test_enqueue_assets_skips_missing_screen(): void
    {
        Functions\expect('current_user_can')->never();
        Functions\when('get_current_screen')->justReturn(null);
        $this->expectNoAssets();

        PostMeta::enqueue_assets('post.php');

        $this->assertFalse(Filters\has('mce_external_plugins'));
    }

    public function test_enqueue_assets_skips_non_post_screen(): void
    {
        Functions\expect('current_user_can')->never();
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

    public function test_enqueue_assets_ignores_corrupt_preview_image_ids(): void
    {
        $this->stubSuccessfulAssetEnqueue(['sidebar_image'], true);
        Functions\when('get_post_meta')->alias(fn($id, $key) => match ($key) {
            '_teksttv_sidebar_image' => ['5'],
            '_thumbnail_id' => '1oops',
            '_teksttv_date_start' => ['2026-08-04'],
            default => '',
        });
        // Core casts '1oops' to attachment 1.
        Functions\when('get_post_thumbnail_id')->justReturn(1);
        Functions\expect('wp_get_attachment_image_url')->never();

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
