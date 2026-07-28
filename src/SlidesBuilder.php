<?php

namespace TekstTV;

use TekstTV\Blocks\BuildContext;

class SlidesBuilder
{
    /**
     * Build ticker messages for a channel.
     *
     * @return list<array<string, string>>
     */
    public static function build_ticker(string $channel_slug): array
    {
        $items = Helpers::get_ticker_config($channel_slug);
        if (empty($items)) {
            return [];
        }

        BuildContext::reset();

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

        BuildContext::reset();

        $slides = [];

        foreach ($blocks as $block) {
            if (!Helpers::is_block_scheduled($block)) {
                continue;
            }

            $type = $block['type'] ?? '';
            $built = BlockRegistry::build($type, $block, $channel_slug);
            $slides = array_merge($slides, $built);
        }

        return array_values(array_filter($slides));
    }
}
