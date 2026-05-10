<?php

namespace TekstTV;

class SlidesBuilder
{
    /**
     * Build ticker messages for a channel.
     *
     * @return list<array<string, string>>
     */
    public static function build_ticker(string $channel_slug): array
    {
        $items = get_option('teksttv_ticker_' . sanitize_key($channel_slug), []);
        if (empty($items) || !is_array($items)) {
            return [];
        }

        $messages = [];
        foreach ($items as $item) {
            if (!Helpers::is_block_scheduled($item)) {
                continue;
            }

            $type = $item['type'] ?? '';
            $built = BlockRegistry::build($type, $item, $channel_slug);
            $messages = array_merge($messages, $built);
        }

        return $messages;
    }

    /**
     * Build all slides for a channel based on its loop configuration.
     *
     * @return list<array<string, mixed>>
     */
    public static function build(string $channel_slug): array
    {
        $blocks = Helpers::get_loop_config($channel_slug);
        if (empty($blocks)) {
            return [];
        }

        $slides = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            $built = BlockRegistry::build($type, $block, $channel_slug);
            $slides = array_merge($slides, $built);
        }

        return array_values(array_filter($slides));
    }
}
