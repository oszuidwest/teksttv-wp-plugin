<?php

namespace TekstTV\Blocks;

/**
 * Shared state for a single slides/ticker build pass.
 *
 * Post-querying blocks (e.g. {@see Loop\ArticlesLoopBlock},
 * {@see Ticker\TickerHeadlinesBlock}) record the post IDs they emit so that
 * subsequent same-context blocks can exclude them, preventing duplicate
 * articles when an operator stacks two of the same block in one channel.
 *
 * Reset at the start of every {@see \TekstTV\SlidesBuilder::build()} and
 * {@see \TekstTV\SlidesBuilder::build_ticker()} call — state is per pass,
 * not global. The loop and ticker passes are independent: a post may appear
 * once in the loop and once in the ticker.
 */
class BuildContext
{
    /** @var array<int, true> Post ID set, kept as a map for O(1) inserts. */
    private static array $seen_post_ids = [];

    public static function reset(): void
    {
        self::$seen_post_ids = [];
    }

    public static function mark_post_seen(int $post_id): void
    {
        if ($post_id > 0) {
            self::$seen_post_ids[$post_id] = true;
        }
    }

    /**
     * @return list<int>
     */
    public static function get_seen_post_ids(): array
    {
        return array_keys(self::$seen_post_ids);
    }
}
