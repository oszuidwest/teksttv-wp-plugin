<?php

namespace TekstTV\Blocks\Common;

use TekstTV\ArticleContent;
use TekstTV\Blocks\BuildContext;
use TekstTV\Helpers;
use WP_Query;

/**
 * Turns batches of queried posts into playout slides for post-backed loop blocks.
 */
final class PostSlides
{
    /** Cap backfill queries when runtime filters reject candidates. */
    private const MAX_QUERY_BATCHES = 10;

    /**
     * Page through on-air candidates until `$count` posts produced slides.
     *
     * @param array<string, mixed>                $block      Stored block; supplies the slide durations.
     * @param array<string, mixed>                $query_args Block-specific WP_Query args such as post type and filters.
     * @param (callable(\WP_Post): bool)|null     $accept     Extra runtime filter applied before preparing a post.
     * @return list<array<string, mixed>>
     */
    public static function collect(array $block, int $count, array $query_args, ?callable $accept = null): array
    {
        $text_duration = Helpers::duration_ms($block['duration_text'] ?? null, 'teksttv_duration_text');
        $image_duration = Helpers::duration_ms($block['duration_image'] ?? null, 'teksttv_duration_image');
        $batch_size = max(10, $count);

        $meta_query = [
            'relation' => 'AND',
            ['key' => '_teksttv_active', 'value' => '1', 'compare' => '='],
        ];
        if (Helpers::has_feature('scheduling')) {
            // SQL pre-filter only; ArticleContent::prepare() is authoritative.
            $meta_query[] = Helpers::get_date_range_meta_query();
        }

        $query_args = array_merge($query_args, [
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'no_found_rows' => true,
            'meta_query' => $meta_query,
            // Keep post__not_in fixed so pagination does not grow SQL/cache keys.
            'post__not_in' => BuildContext::get_seen_post_ids(),
            'orderby' => ['date' => 'DESC', 'ID' => 'DESC'],
        ]);

        $slides = [];
        $emitted_post_count = 0;

        for ($batch = 0; $batch < self::MAX_QUERY_BATCHES && $emitted_post_count < $count; $batch++) {
            $query = new WP_Query(['paged' => $batch + 1] + $query_args);

            ArticleContent::prime_images($query->posts);

            while ($emitted_post_count < $count && $query->have_posts()) {
                $query->the_post();
                $post = get_post();
                if (!$post || ($accept !== null && !$accept($post))) {
                    continue;
                }

                $article = ArticleContent::prepare($post);
                if ($article === null) {
                    continue;
                }

                array_push($slides, ...self::from_article($article, $text_duration, $image_duration));
                BuildContext::mark_post_seen($post->ID);
                $emitted_post_count++;
            }

            wp_reset_postdata();
            if (count($query->posts) < $batch_size) {
                break;
            }
        }

        return $slides;
    }

    /**
     * @param array{title: string, pages: list<string>, sidebar_image: array<string, string>|null, images: list<array<string, string>>} $article
     * @return list<array<string, mixed>>
     */
    public static function from_article(array $article, int $text_duration, int $image_duration): array
    {
        $slides = [];
        foreach ($article['pages'] as $page_content) {
            $slide = [
                'type' => 'text',
                'duration' => $text_duration,
                'title' => $article['title'],
                'body' => wpautop($page_content),
            ];
            if ($article['sidebar_image'] !== null) {
                $slide['image'] = $article['sidebar_image'];
            }
            $slides[] = $slide;
        }
        foreach ($article['images'] as $image) {
            $slides[] = array_merge(['type' => 'image', 'duration' => $image_duration], $image);
        }

        return $slides;
    }
}
