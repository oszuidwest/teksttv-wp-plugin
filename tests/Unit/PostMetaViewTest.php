<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\PostMeta;

class PostMetaViewTest extends TestCase
{
    /** @var list<string> */
    private const RICH_TEXT_FEATURES = ['bold', 'italic', 'underline', 'lists'];

    /**
     * @param list<string> $features
     */
    private static function stubView(array $features): void
    {
        Functions\when('get_option')->alias(
            fn ($key, $default = false) => $key === 'teksttv_features' ? $features : $default
        );
        Functions\stubEscapeFunctions();
    }

    /**
     * @param list<string> $features
     * @param list<array{slug: string, label: string}> $channels
     * @param list<string> $selected_channels
     */
    private static function renderView(array $features, bool $is_message = false, array $channels = [], array $selected_channels = []): string
    {
        self::stubView($features);
        Functions\when('checked')->alias(static function (mixed $checked, mixed $current = true): void {
            echo $checked === $current ? ' checked' : '';
        });
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('wp_editor')->alias(static function (string $content, string $editor_id): void {
            echo '<div id="wp-' . htmlspecialchars($editor_id, ENT_QUOTES, 'UTF-8') . '-wrap">'
                . htmlspecialchars($content, ENT_QUOTES, 'UTF-8')
                . '</div>';
        });

        $post = self::makePost();
        $active = '';
        $title = '';
        $content = '';
        $date_start = '';
        $date_end = '';
        $days = null;
        $images = [];
        $schedule_invalid = false;
        $preview_url = 'https://example.com/preview';
        $ai_enabled = !$is_message;
        $toolbar_items = [];
        $valid_elements = ['br', 'p'];
        $use_tinymce = array_intersect(self::RICH_TEXT_FEATURES, $features) !== [];
        $plain_content = $use_tinymce ? '' : 'Bestaande tekst';

        ob_start();
        include dirname(__DIR__, 2) . '/src/views/post-meta-box.php';
        return (string) ob_get_clean();
    }

    public function test_corrupt_meta_renders_like_playout_reads_it(): void
    {
        $meta = [
            '_teksttv_title' => ['kop'],
            '_teksttv_content' => new \stdClass(),
            '_teksttv_date_start' => '2025-1-5',
            '_teksttv_days' => [1, ['2'], 3, new \stdClass()],
            '_teksttv_images' => [[12], 11],
            '_teksttv_sidebar_image' => ['5'],
            '_thumbnail_id' => ['7'],
        ];
        self::stubView(['custom_title', 'sidebar_image', 'extra_images', 'scheduling']);
        Functions\when('get_post_meta')->alias(function ($id, $key) use (&$meta) { return $meta[$key] ?? ''; });
        Functions\when('wp_nonce_field')->justReturn('');
        Functions\when('checked')->alias(static function (mixed $checked, mixed $current = true): void {
            echo $checked === $current ? ' checked' : '';
        });
        Functions\when('admin_url')->returnArg();
        Functions\when('wp_kses')->returnArg();
        // Core casts a corrupt array ID to attachment 1.
        Functions\when('wp_get_attachment_image_url')->alias(fn ($id) => 'https://example.com/' . (int) $id . '.jpg');

        ob_start();
        PostMeta::render_meta_box(self::makePost());
        $view = (string) ob_get_clean();

        $this->assertStringContainsString('De opgeslagen planning is ongeldig', $view);
        $this->assertStringContainsString('name="teksttv_title" id="teksttv-title" value=""', $view);
        $this->assertStringContainsString('id="teksttv-sidebar-image-id" value=""', $view);
        $this->assertStringContainsString('data-active="default"', $view);
        preg_match_all('/name="teksttv_images\[\]" value="(\d+)"/', $view, $image_ids);
        $this->assertSame(['11'], $image_ids[1]);
        $this->assertStringNotContainsString('/1.jpg', $view);
        preg_match_all('/value="(\d)" class="teksttv-visually-hidden"\s+checked/', $view, $checked_days);
        $this->assertSame(['1', '3'], $checked_days[1]);

        $meta['_teksttv_date_start'] = '2025-01-05';
        $meta['_teksttv_days'] = [1, 3];
        $meta['_thumbnail_id'] = '7';
        Functions\when('get_post_thumbnail_id')->justReturn(7);
        Functions\when('get_the_post_thumbnail_url')->justReturn('https://cdn.example.com/7.jpg');
        ob_start();
        PostMeta::render_meta_box(self::makePost());
        $view = (string) ob_get_clean();
        $this->assertStringNotContainsString('De opgeslagen planning is ongeldig', $view);
        $this->assertStringContainsString('https://cdn.example.com/7.jpg', $view);

        $meta['_teksttv_days'] = ['99'];
        ob_start();
        PostMeta::render_meta_box(self::makePost());
        $view = (string) ob_get_clean();
        $this->assertStringContainsString('De opgeslagen planning is ongeldig', $view);
    }

    public function test_article_view_shows_the_on_air_toggle_and_no_channels(): void
    {
        $view = self::renderView(['custom_title']);

        $this->assertStringContainsString('<div class="teksttv-toggle-bar">', $view);
        $this->assertStringContainsString('name="teksttv_active"', $view);
        $this->assertStringContainsString('name="teksttv_title"', $view);
        $this->assertStringNotContainsString('teksttv_channels[]', $view);
    }

    public function test_message_view_hides_toggle_and_custom_title_and_offers_channels(): void
    {
        $channels = [['slug' => 'tv1', 'label' => 'TV 1'], ['slug' => 'tv2', 'label' => 'TV 2']];
        $view = self::renderView(['custom_title', 'ai_generate'], true, $channels, ['tv2']);

        $this->assertStringNotContainsString('teksttv-toggle-bar', $view);
        $this->assertStringNotContainsString('name="teksttv_active"', $view);
        $this->assertStringNotContainsString('name="teksttv_title"', $view);
        $this->assertStringNotContainsString('teksttv-generate-btn', $view);
        $this->assertMatchesRegularExpression('/name="teksttv_channels\[\]" value="tv1"\s+\/>/', $view);
        $this->assertMatchesRegularExpression('/name="teksttv_channels\[\]" value="tv2"\s+checked \/>/', $view);
    }

    public function test_message_view_offers_no_default_sidebar_image(): void
    {
        Functions\when('get_post_thumbnail_id')->justReturn(0);
        Functions\when('wp_get_attachment_image_url')->justReturn(false);

        $article = self::renderView(['sidebar_image']);
        $message = self::renderView(['sidebar_image'], true);

        $this->assertStringContainsString('data-state="default"', $article);
        $this->assertStringContainsString('data-active="default"', $article);
        $this->assertStringNotContainsString('data-state="default"', $message);
        // Without a stored choice a message shows no image, so "Geen" is the active card.
        $this->assertStringContainsString('data-active="none"', $message);
        $this->assertStringContainsString('id="teksttv-sidebar-image-id" value="0"', $message);
    }

    public function test_message_view_omits_channel_choice_for_a_single_channel(): void
    {
        $view = self::renderView([], true, [['slug' => 'tv1', 'label' => 'TV 1']]);

        $this->assertStringNotContainsString('teksttv_channels[]', $view);
    }

    public function test_message_view_warns_when_only_removed_channels_are_ticked(): void
    {
        $view = self::renderView([], true, [['slug' => 'tv1', 'label' => 'TV 1']], ['tv2']);

        // Shown despite a single channel, so the editor can see and fix the choice.
        $this->assertStringContainsString('name="teksttv_channels_shown"', $view);
        $this->assertStringContainsString('staat alleen op kanalen die niet meer bestaan (tv2)', $view);
    }

    public function test_message_view_does_not_warn_while_a_ticked_channel_still_exists(): void
    {
        $channels = [['slug' => 'tv1', 'label' => 'TV 1'], ['slug' => 'tv2', 'label' => 'TV 2']];
        $view = self::renderView([], true, $channels, ['tv1', 'tv9']);

        $this->assertStringNotContainsString('niet meer bestaan', $view);
    }

    public function test_combined_ai_action_includes_title_when_custom_titles_are_enabled(): void
    {
        $view = self::renderView(['ai_generate', 'custom_title']);

        $this->assertMatchesRegularExpression(
            '/teksttv-ai-section[^>]*>\s*<button[^>]*data-field="both"[^>]*>.*?Genereer kop &amp; tekst<\/button>/s',
            $view
        );
        $this->assertStringContainsString('data-field="title"', $view);
        $this->assertSame(3, substr_count($view, 'teksttv-generate-btn'));
    }

    public function test_combined_ai_action_is_body_only_when_custom_titles_are_disabled(): void
    {
        $view = self::renderView(['ai_generate']);

        $this->assertMatchesRegularExpression(
            '/teksttv-ai-section[^>]*>\s*<button[^>]*data-field="body"[^>]*>.*?Genereer tekst<\/button>/s',
            $view
        );
        $this->assertStringNotContainsString('data-field="both"', $view);
        $this->assertStringNotContainsString('data-field="title"', $view);
        $this->assertStringNotContainsString('Genereer kop &amp; tekst', $view);
        $this->assertSame(1, substr_count($view, 'teksttv-generate-btn'));
    }

    public function test_plain_editor_replaces_tinymce_when_rich_text_is_disabled(): void
    {
        $view = self::renderView(['custom_title']);

        $this->assertStringContainsString('class="large-text teksttv-plain-editor"', $view);
        $this->assertStringContainsString('>Bestaande tekst</textarea>', $view);
        $this->assertStringNotContainsString('wp-teksttv_content-wrap', $view);
    }

    public function test_tinymce_replaces_plain_editor_when_rich_text_is_enabled(): void
    {
        $view = self::renderView(['bold']);

        $this->assertStringContainsString('id="wp-teksttv_content-wrap"', $view);
        $this->assertStringNotContainsString('teksttv-plain-editor', $view);
    }

    public function test_title_instruction_is_a_placeholder_instead_of_help_text(): void
    {
        $view = self::renderView(['custom_title']);

        $this->assertStringContainsString(
            'placeholder="Laat leeg om de titel van het artikel te gebruiken."',
            $view
        );
    }
}
