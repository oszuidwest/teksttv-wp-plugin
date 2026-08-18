<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;

class PostMetaViewTest extends TestCase
{
    /** @var list<string> */
    private const RICH_TEXT_FEATURES = ['bold', 'italic', 'underline', 'lists'];

    /**
     * @param list<string> $features
     */
    private static function renderView(array $features): string
    {
        Functions\when('get_option')->alias(
            fn ($key, $default = false) => $key === 'teksttv_features' ? $features : $default
        );
        Functions\when('checked')->justReturn('');
        Functions\when('esc_html')->alias(fn ($text) => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_attr')->alias(fn ($text) => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_textarea')->alias(fn ($text) => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('wp_editor')->alias(static function (string $content, string $editor_id): void {
            echo '<div id="wp-' . htmlspecialchars($editor_id, ENT_QUOTES, 'UTF-8') . '-wrap">'
                . htmlspecialchars($content, ENT_QUOTES, 'UTF-8')
                . '</div>';
        });

        $post = self::makePost();
        $active = '';
        $content = '';
        $date_start = '';
        $date_end = '';
        $days = null;
        $images = [];
        $preview_url = 'https://example.com/preview';
        $ai_enabled = true;
        $toolbar_items = [];
        $valid_elements = ['br', 'p'];
        $use_tinymce = array_intersect(self::RICH_TEXT_FEATURES, $features) !== [];
        $plain_content = $use_tinymce ? '' : 'Bestaande tekst';

        ob_start();
        include dirname(__DIR__, 2) . '/src/views/post-meta-box.php';
        return (string) ob_get_clean();
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
