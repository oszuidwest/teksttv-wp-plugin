<?php

namespace TekstTV;

/**
 * Standalone TekstTV messages: slides that are not tied to a website article.
 */
final class Messages
{
    public const POST_TYPE = 'teksttv_message';
    public const FEATURE = 'messages';
    public const CHANNELS_META = '_teksttv_channels';

    /**
     * Primitive capabilities of the message post type. Kept separate from
     * edit_teksttv so a role plugin can grant messages without article access.
     *
     * @var list<string>
     */
    public const CAPABILITIES = [
        'edit_teksttv_messages',
        'edit_others_teksttv_messages',
        'edit_published_teksttv_messages',
        'edit_private_teksttv_messages',
        'publish_teksttv_messages',
        'read_private_teksttv_messages',
        'delete_teksttv_messages',
        'delete_others_teksttv_messages',
        'delete_published_teksttv_messages',
        'delete_private_teksttv_messages',
    ];

    /** @var list<string> */
    public const DEFAULT_ROLES = ['administrator', 'editor'];

    public static function init(): void
    {
        add_action('init', [self::class, 'register_post_type']);
    }

    public static function enabled(): bool
    {
        return Helpers::has_feature(self::FEATURE);
    }

    public static function register_post_type(): void
    {
        if (!self::enabled()) {
            return;
        }

        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'Tekst TV-berichten',
                'singular_name' => 'Tekst TV-bericht',
                'menu_name' => 'Tekst TV-berichten',
                'all_items' => 'Alle berichten',
                'add_new' => 'Nieuw bericht',
                'add_new_item' => 'Nieuw Tekst TV-bericht',
                'edit_item' => 'Tekst TV-bericht bewerken',
                'new_item' => 'Nieuw Tekst TV-bericht',
                'search_items' => 'Berichten zoeken',
                'not_found' => 'Geen berichten gevonden.',
                'not_found_in_trash' => 'Geen berichten in de prullenbak.',
            ],
            'description' => 'Losse berichten voor Tekst TV, zonder artikel op de website.',
            // Playout only: no front-end URL, search result, feed or core REST route.
            'public' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => false,
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-format-aside',
            'menu_position' => 31,
            'supports' => ['title'],
            'capability_type' => ['teksttv_message', 'teksttv_messages'],
            'map_meta_cap' => true,
            'rewrite' => false,
            'query_var' => false,
            'can_export' => true,
        ]);
    }

    /**
     * Grant the message capabilities to the default roles.
     */
    public static function grant_capabilities(): void
    {
        foreach (self::DEFAULT_ROLES as $role_name) {
            $role = get_role($role_name);
            if (!$role) {
                continue;
            }
            foreach (self::CAPABILITIES as $capability) {
                $role->add_cap($capability);
            }
        }
    }

    /**
     * Remove the message capabilities from the default roles. Grants a role plugin made
     * to other roles survive, because activation would not restore them.
     */
    public static function revoke_capabilities(): void
    {
        foreach (self::DEFAULT_ROLES as $role_name) {
            $role = get_role($role_name);
            if (!$role) {
                continue;
            }
            foreach (self::CAPABILITIES as $capability) {
                $role->remove_cap($capability);
            }
        }
    }

    /**
     * Stored channel slugs; an empty list means every channel.
     *
     * @return list<string>
     */
    public static function channels(int $post_id): array
    {
        $raw = get_post_meta($post_id, self::CHANNELS_META, true);
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, static fn($slug): bool => is_string($slug) && $slug !== ''));
    }

    public static function airs_on(int $post_id, string $channel): bool
    {
        $channels = self::channels($post_id);
        return $channels === [] || in_array($channel, $channels, true);
    }

    /**
     * Keep only configured channel slugs.
     *
     * @return list<string>
     */
    public static function sanitize_channels(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $slugs = array_map(static fn($slug): string => is_scalar($slug) ? sanitize_key((string) $slug) : '', $raw);
        return array_values(array_unique(array_intersect($slugs, Helpers::channel_slugs())));
    }
}
