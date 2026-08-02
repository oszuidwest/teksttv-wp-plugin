<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;

class PostMetaViewTest extends TestCase
{
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
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_the_title')->justReturn('Titel');
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('wp_editor')->justReturn(null);

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
    }
}
