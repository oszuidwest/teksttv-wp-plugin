<?php

namespace TekstTV\Blocks;

/**
 * Track emitted post IDs within a build to prevent duplicates.
 */
class BuildContext
{
    /** @var array<int, true> Post ID set for O(1) inserts. */
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
