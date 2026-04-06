<?php

namespace TekstTV;

/**
 * Registry for loop and ticker block types.
 *
 * Block types are registered with a slug, label, icon, context (loop/ticker/both),
 * and callbacks for rendering admin fields, saving POST data, and building slides/messages.
 *
 * Usage:
 *   BlockRegistry::register('my_block', [
 *       'label'   => 'My Block',
 *       'icon'    => 'dashicons-admin-generic',
 *       'color'   => '#8c8f94',
 *       'context' => 'loop',         // 'loop', 'ticker', or 'both'
 *       'render'  => function (int|string $index, array $data, string $prefix): void { ... },
 *       'save'    => function (array $raw): ?array { ... },
 *       'build'   => function (array $data, string $channel): array { ... },
 *   ]);
 */
class BlockRegistry
{
    /** @var array<string, array<string, mixed>> */
    private static array $types = [];

    /**
     * Register a block type.
     *
     * @param string                $slug  Unique block type identifier.
     * @param array<string, mixed>  $args  {
     *     @type string   $label   Display label.
     *     @type string   $icon    Dashicon class name (without 'dashicons-' prefix).
     *     @type string   $color   Icon background color (hex).
     *     @type string   $context 'loop', 'ticker', or 'both'.
     *     @type callable $render  Renders admin form fields. Receives ($index, $data, $prefix).
     *                             $prefix is 'teksttv_blocks' or 'teksttv_ticker'.
     *     @type callable $save    Sanitizes POST data. Receives ($raw_data). Returns sanitized array or null to skip.
     *     @type callable $build   Builds slides or ticker messages. Receives ($data, $channel).
     *                             Returns array of slides (loop) or array of ticker messages (ticker).
     * }
     */
    public static function register(string $slug, array $args): void
    {
        $args = wp_parse_args($args, [
            'label' => $slug,
            'icon' => 'admin-generic',
            'color' => '#8c8f94',
            'context' => 'loop',
            'render' => null,
            'save' => null,
            'build' => null,
        ]);

        self::$types[$slug] = $args;
    }

    /**
     * Get a registered block type.
     *
     * @return array<string, mixed>|null
     */
    public static function get(string $slug): ?array
    {
        return self::$types[$slug] ?? null;
    }

    /**
     * Get all registered block types, optionally filtered by context.
     *
     * @param string|null $context 'loop', 'ticker', or null for all.
     * @return array<string, array<string, mixed>>
     */
    public static function all(?string $context = null): array
    {
        if ($context === null) {
            return self::$types;
        }

        return array_filter(self::$types, function ($args) use ($context) {
            return $args['context'] === $context || $args['context'] === 'both';
        });
    }

    /**
     * Render the admin form fields for a block.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $slug, int|string $index, array $data, string $prefix = 'teksttv_blocks'): void
    {
        $type = self::get($slug);
        if (!$type || !is_callable($type['render'])) {
            return;
        }

        call_user_func($type['render'], $index, $data, $prefix);
    }

    /**
     * Sanitize and save a block's POST data.
     *
     * @param array<string, mixed> $raw_data
     * @return array<string, mixed>|null Sanitized block data, or null to skip.
     */
    public static function save(string $slug, array $raw_data): ?array
    {
        $type = self::get($slug);
        if (!$type || !is_callable($type['save'])) {
            return null;
        }

        $saved = call_user_func($type['save'], $raw_data);
        if (!is_array($saved)) {
            return null;
        }

        $saved['type'] = $slug;
        return $saved;
    }

    /**
     * Build slides or ticker messages from a block.
     *
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>> Array of slides (loop context) or ticker messages (ticker context).
     */
    public static function build(string $slug, array $data, string $channel = ''): array
    {
        $type = self::get($slug);
        if (!$type || !is_callable($type['build'])) {
            return [];
        }

        return call_user_func($type['build'], $data, $channel) ?: [];
    }
}
