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
            'ai_generate',
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
     * Get the AI prompt configuration with defaults.
     *
     * @return array{system: string, prompt_title: string, prompt_body: string, word_limit: int, min_input_words: int}
     */
    public static function get_ai_prompts(): array
    {
        $saved = get_option('teksttv_ai_prompts', []);
        $word_limit = max(10, (int) ($saved['word_limit'] ?? 100));
        $title_char_limit = max(10, (int) ($saved['title_char_limit'] ?? 40));
        $min_input = max(0, (int) ($saved['min_input_words'] ?? 50));
        $max_retries = max(1, min(5, (int) ($saved['max_retries'] ?? 3)));
        $rate_limit = max(1, min(60, (int) ($saved['rate_limit'] ?? 10)));

        $defaults = [
            'system' => 'Je bent een eindredacteur voor tekst-tv. Schrijf in natuurlijk, vloeiend Nederlands voor een breed publiek. Gebruik korte, heldere zinnen. Schrijf alleen in het Nederlands en gebruik geen gedachtestreepjes.',
            'prompt_title' => sprintf(
                'Schrijf een korte, pakkende kop voor tekst-tv (maximaal %d tekens) gebaseerd op dit artikel. Geef alleen de kop terug, zonder aanhalingstekens.',
                $title_char_limit
            ),
            'prompt_body' => sprintf(
                'Vat dit nieuwsartikel samen voor tekst-tv in maximaal %d woorden. Schrijf in vloeiende, korte zinnen zonder HTML-opmaak.',
                $word_limit
            ),
        ];

        return [
            'system' => !empty($saved['system']) ? $saved['system'] : $defaults['system'],
            'prompt_title' => !empty($saved['prompt_title']) ? $saved['prompt_title'] : $defaults['prompt_title'],
            'prompt_body' => !empty($saved['prompt_body']) ? $saved['prompt_body'] : $defaults['prompt_body'],
            'word_limit' => $word_limit,
            'title_char_limit' => $title_char_limit,
            'min_input_words' => $min_input,
            'max_retries' => $max_retries,
            'rate_limit' => $rate_limit,
            'region_taxonomy' => $saved['region_taxonomy'] ?? '',
            'provider' => $saved['provider'] ?? '',
            'model' => $saved['model'] ?? '',
            'temperature' => $saved['temperature'] ?? '',
            'top_p' => $saved['top_p'] ?? '',
            'max_tokens' => max(64, (int) ($saved['max_tokens'] ?? 2048)),
        ];
    }

    /**
     * Get available AI models grouped by provider.
     *
     * @return array<string, array{label: string, models: array<string, string>}>
     */
    public static function get_ai_models(): array
    {
        if (!function_exists('wp_supports_ai') || !wp_supports_ai()) {
            return [];
        }

        try {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
                [\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration()],
                []
            );

            $result = [];
            foreach ($registry->findModelsMetadataForSupport($requirements) as $provider_models) {
                $provider = $provider_models->getProvider();
                $provider_id = $provider->getId();
                $models = [];
                foreach ($provider_models->getModels() as $model) {
                    $models[$model->getId()] = $model->getName();
                }
                if (!empty($models)) {
                    $result[$provider_id] = [
                        'label' => $provider->getName(),
                        'models' => $models,
                    ];
                }
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
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
     * Get configured campaign groups.
     *
     * @return string[] Array of group labels.
     */
    public static function get_campaign_groups(): array
    {
        $groups = get_option('teksttv_campaign_groups', []);
        if (!empty($groups) && is_array($groups)) {
            return array_values($groups);
        }

        return [];
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
