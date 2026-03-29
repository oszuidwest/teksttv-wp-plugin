<?php

namespace TekstTV;

use DateTime;
use DateTimeInterface;

class Helpers
{
    /**
     * Check if content should be displayed on the given day.
     *
     * @param array|null $allowed_days ISO-8601 day numbers (1=Mon, 7=Sun) or empty for "no days"
     * @param DateTimeInterface|null $date Date to check, defaults to current date
     */
    public static function is_allowed_on_day(?array $allowed_days, ?DateTimeInterface $date = null): bool
    {
        if (empty($allowed_days)) {
            return true;
        }

        $date = $date ?? current_datetime();
        $current_day = $date->format('N');

        return in_array((string) $current_day, array_map('strval', $allowed_days), true);
    }

    /**
     * Check if current date falls within an optional date range.
     * Empty values mean no restriction on that side.
     *
     * @param string|null $start_date Y-m-d format
     * @param string|null $end_date Y-m-d format
     */
    public static function is_within_date_range(?string $start_date, ?string $end_date): bool
    {
        $now = current_datetime();
        $timezone = wp_timezone();

        if (!empty($start_date)) {
            $start = DateTime::createFromFormat('Y-m-d', trim($start_date), $timezone);
            if ($start && $now < $start->setTime(0, 0, 0)) {
                return false;
            }
        }

        if (!empty($end_date)) {
            $end = DateTime::createFromFormat('Y-m-d', trim($end_date), $timezone);
            if ($end && $now > $end->setTime(23, 59, 59)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all WP categories as id => name array for use in dropdowns.
     */
    public static function get_category_options(): array
    {
        $categories = get_categories(['hide_empty' => false]);
        $options = [0 => __('Alle categorieën', 'teksttv')];
        foreach ($categories as $cat) {
            $options[$cat->term_id] = $cat->name;
        }
        return $options;
    }

    /**
     * Get the stored channels list.
     *
     * @return array Array of ['slug' => string, 'label' => string]
     */
    public static function get_channels(): array
    {
        $channels = get_option('teksttv_channels', []);
        if (empty($channels)) {
            return [['slug' => 'tv1', 'label' => 'TV 1']];
        }
        return $channels;
    }

    /**
     * Get the enabled features.
     *
     * @return array Array of feature slugs
     */
    public static function get_features(): array
    {
        return get_option('teksttv_features', [
            'custom_title', 'sidebar_image', 'extra_images',
            'scheduling', 'page_separator',
            'bold', 'italic', 'underline', 'lists',
        ]);
    }

    /**
     * Check if a feature is enabled.
     */
    public static function has_feature(string $feature): bool
    {
        return in_array($feature, self::get_features(), true);
    }

    /**
     * Get the loop configuration for a channel.
     *
     * @return array Array of block definitions
     */
    public static function get_loop_config(string $channel_slug): array
    {
        return get_option('teksttv_loop_' . sanitize_key($channel_slug), []);
    }

    /**
     * Get all campaigns.
     */
    public static function get_campaigns(): array
    {
        return get_option('teksttv_campaigns', []);
    }

    /**
     * Get active campaigns for a specific channel.
     * Filters by channel assignment and date range.
     */
    public static function get_active_campaigns(string $channel): array
    {
        $campaigns = self::get_campaigns();

        return array_filter($campaigns, function ($campaign) use ($channel) {
            // Must be assigned to this channel
            $channels = $campaign['channels'] ?? [];
            if (!in_array($channel, $channels, true)) {
                return false;
            }

            // Must be within date range
            return self::is_within_date_range(
                $campaign['date_start'] ?? null,
                $campaign['date_end'] ?? null
            );
        });
    }

    /**
     * Get the preview base URL from settings.
     */
    public static function get_preview_url(): string
    {
        return get_option('teksttv_preview_url', '');
    }
}
