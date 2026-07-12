<?php

namespace TekstTV;

class CategoryMeta
{
    private const META_KEY = '_teksttv_category_image';

    public static function init(): void
    {
        add_action('category_add_form_fields', [self::class, 'render_add_form_field']);
        add_action('category_edit_form_fields', [self::class, 'render_edit_form_field']);
        add_action('created_category', [self::class, 'save_term_meta']);
        add_action('edited_category', [self::class, 'save_term_meta']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function enqueue_assets(string $hook): void
    {
        if (!in_array($hook, ['edit-tags.php', 'term.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->taxonomy !== 'category') {
            return;
        }

        Helpers::enqueue_admin_script();
    }

    public static function render_add_form_field(): void
    {
        wp_nonce_field('teksttv_category_image', 'teksttv_category_nonce');
        ?>
        <div class="form-field">
            <label for="teksttv-cat-image-id"><?php esc_html_e('Tekst TV afbeelding', 'teksttv-wp-plugin'); ?></label>
            <div x-data="teksttvCategoryMedia">
            <p>
                <input type="hidden" name="teksttv_category_image" id="teksttv-cat-image-id" value="" />
                <img id="teksttv-cat-image-preview" class="teksttv-cat-image-preview is-hidden" src="" />
                <br />
                <button type="button" class="button" id="teksttv-cat-image-select" @click="pickImage($event)"><?php esc_html_e('Afbeelding kiezen', 'teksttv-wp-plugin'); ?></button>
                <button type="button" class="button is-hidden" id="teksttv-cat-image-remove" @click="clearImage($event)"><?php esc_html_e('Verwijderen', 'teksttv-wp-plugin'); ?></button>
            </p>
            <p class="description"><?php esc_html_e('Sidebar-afbeelding voor artikelen in deze categorie op Tekst TV.', 'teksttv-wp-plugin'); ?></p>
            </div>
        </div>
        <?php
    }

    public static function render_edit_form_field(\WP_Term $term): void
    {
        $image_id = get_term_meta($term->term_id, self::META_KEY, true);
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

        wp_nonce_field('teksttv_category_image', 'teksttv_category_nonce');
        ?>
        <tr class="form-field">
            <th scope="row"><label for="teksttv-cat-image-id"><?php esc_html_e('Tekst TV afbeelding', 'teksttv-wp-plugin'); ?></label></th>
            <td>
                <div x-data="teksttvCategoryMedia">
                <input type="hidden" name="teksttv_category_image" id="teksttv-cat-image-id" value="<?php echo esc_attr($image_id); ?>" />
                <img id="teksttv-cat-image-preview" class="teksttv-cat-image-preview <?php echo $image_url ? '' : 'is-hidden'; ?>" src="<?php echo esc_url($image_url); ?>" />
                <br />
                <button type="button" class="button" id="teksttv-cat-image-select" @click="pickImage($event)"><?php esc_html_e('Afbeelding kiezen', 'teksttv-wp-plugin'); ?></button>
                <button type="button" class="button <?php echo $image_url ? '' : 'is-hidden'; ?>" id="teksttv-cat-image-remove" @click="clearImage($event)"><?php esc_html_e('Verwijderen', 'teksttv-wp-plugin'); ?></button>
                <p class="description"><?php esc_html_e('Sidebar-afbeelding voor artikelen in deze categorie op Tekst TV.', 'teksttv-wp-plugin'); ?></p>
                </div>
            </td>
        </tr>
        <?php
    }

    public static function save_term_meta(int $term_id): void
    {
        if (
            !isset($_POST['teksttv_category_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['teksttv_category_nonce'])), 'teksttv_category_image')
        ) {
            return;
        }

        if (!current_user_can('manage_categories')) {
            return;
        }

        if (!isset($_POST['teksttv_category_image'])) {
            return;
        }

        $image_id = absint(wp_unslash($_POST['teksttv_category_image']));
        if ($image_id) {
            update_term_meta($term_id, self::META_KEY, $image_id);
        } else {
            delete_term_meta($term_id, self::META_KEY);
        }

        // The category image feeds the article sidebar fallback.
        RestApi::invalidate_slides_cache();
    }
}
