<?php
/**
 * Post meta box template.
 *
 * @var \WP_Post $post
 * @var string $active
 * @var string $content
 * @var string $date_start
 * @var string $date_end
 * @var list<string>|null $days
 * @var list<int> $images
 * @var string $preview_url
 * @var bool $ai_enabled
 * @var list<string> $toolbar_items
 * @var list<string> $valid_elements
 */

namespace TekstTV;

defined('ABSPATH') || exit;

$has_custom_title = Helpers::has_feature('custom_title');

?>
<div class="teksttv-meta-box" x-data="teksttvPostMetaPage">
    <div class="teksttv-toggle-bar">
        <label>
            <input type="checkbox" name="teksttv_active" value="1" <?php checked($active, '1'); ?> id="teksttv-active" @change="onActiveChange()" />
            <span class="dashicons dashicons-desktop" aria-hidden="true"></span>
            <?php echo esc_html('Toon op Tekst TV'); ?>
        </label>
    </div>

    <div class="teksttv-fields" id="teksttv-fields">
        <!-- Two-column layout: editor left, preview right -->
        <div class="teksttv-editor-layout">
            <div class="teksttv-editor-main">
                <?php if ($ai_enabled) : ?>
                <div class="teksttv-meta-section teksttv-ai-section">
                    <button type="button" class="button button-small teksttv-generate-btn" data-field="<?php echo esc_attr($has_custom_title ? 'both' : 'body'); ?>" @click.prevent="onGenerateClick($event)"><?php echo esc_html($has_custom_title ? 'Genereer kop & tekst' : 'Genereer tekst'); ?></button>
                    <span class="teksttv-generate-status" id="teksttv-generate-status" role="status" aria-live="polite"></span>
                </div>
                <?php endif; ?>

                <?php if ($has_custom_title) : ?>
                <!-- Title override -->
                <div class="teksttv-meta-section">
                    <div class="teksttv-section-header">
                        <label for="teksttv-title" class="teksttv-section-label"><?php echo esc_html('Kop'); ?></label>
                        <?php if ($ai_enabled) : ?>
                        <button type="button" class="button button-small teksttv-generate-btn" data-field="title" @click.prevent="onGenerateClick($event)"><?php echo esc_html('Genereer'); ?></button>
                        <?php endif; ?>
                    </div>
                    <?php $custom_title = get_post_meta($post->ID, '_teksttv_title', true); ?>
                    <input type="text" name="teksttv_title" id="teksttv-title" value="<?php echo esc_attr($custom_title); ?>" class="large-text" placeholder="<?php echo esc_attr(get_the_title($post)); ?>" @input="onTitleInputMeta()" />
                    <div class="teksttv-title-footer">
                        <p class="description"><?php echo esc_html('Laat leeg om de titel van het artikel te gebruiken.'); ?></p>
                        <span class="teksttv-charcount" id="teksttv-charcount"></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Content -->
                <div class="teksttv-meta-section teksttv-content-wrap">
                    <div class="teksttv-section-header">
                        <label for="teksttv_content" class="teksttv-section-label"><?php echo esc_html('Tekst voor Tekst TV'); ?></label>
                        <?php if ($ai_enabled) : ?>
                        <button type="button" class="button button-small teksttv-generate-btn" data-field="body" @click.prevent="onGenerateClick($event)"><?php echo esc_html('Genereer'); ?></button>
                        <?php endif; ?>
                    </div>
                    <?php
                    wp_editor($content, 'teksttv_content', [
                        'textarea_name' => 'teksttv_content',
                        'textarea_rows' => 4,
                        'editor_height' => 100,
                        'media_buttons' => false,
                        'teeny' => false,
                        'quicktags' => ['buttons' => implode(',', array_filter([
                            Helpers::has_feature('bold') ? 'strong' : '',
                            Helpers::has_feature('italic') ? 'em' : '',
                            Helpers::has_feature('lists') ? 'ul' : '',
                            Helpers::has_feature('lists') ? 'ol' : '',
                            Helpers::has_feature('lists') ? 'li' : '',
                            'close',
                        ]))
                        ],
                        'tinymce' => [
                            'toolbar1' => implode(',', $toolbar_items),
                            'toolbar2' => '',
                            'toolbar3' => '',
                            'toolbar4' => '',
                            'block_formats' => '',
                            'valid_elements' => implode(',', $valid_elements),
                            'formats' => wp_json_encode([
                                'underline' => ['inline' => 'u'],
                            ]),
                            'forced_root_block' => 'p',
                            'plugins' => 'lists,paste,wpautoresize',
                            'wp_autoresize_on' => true,
                            'autoresize_min_height' => 100,
                            'autoresize_max_height' => 350,
                            'content_css' => TEKSTTV_PLUGIN_URL . 'assets/tinymce-content.css',
                            'content_style' => 'body { margin: 0 !important; padding: 6px 8px !important; } body p { margin: 0 0 0.5em !important; }',
                        ],
                    ]);
                    ?>
                    <div class="teksttv-editor-footer">
                        <span class="teksttv-wordcount" id="teksttv-wordcount"></span>
                    </div>
                </div>

                <?php if (Helpers::has_feature('sidebar_image')) : ?>
                <!-- Sidebar image -->
                <div class="teksttv-meta-section teksttv-sidebar-image-section">
                    <span class="teksttv-section-label"><?php echo esc_html('Sidebar afbeelding'); ?></span>
                    <?php
                    $sidebar_image_id = get_post_meta($post->ID, '_teksttv_sidebar_image', true);
                    $is_none = $sidebar_image_id === '0';
                    $is_custom = $sidebar_image_id !== '' && $sidebar_image_id !== false && !$is_none;
                    $custom_url = $is_custom ? wp_get_attachment_image_url((int) $sidebar_image_id, 'medium') : '';
                    $fallback_url = get_the_post_thumbnail_url($post->ID, 'medium') ?: '';
                    $active_state = $is_none ? 'none' : ($is_custom ? 'custom' : 'default');
                    ?>
                    <input type="hidden" name="teksttv_sidebar_image" id="teksttv-sidebar-image-id" value="<?php echo esc_attr($sidebar_image_id); ?>" />
                    <div class="teksttv-image-cards" data-active="<?php echo esc_attr($active_state); ?>" role="group" aria-label="<?php echo esc_attr('Sidebarafbeelding kiezen'); ?>">
                        <button type="button" class="teksttv-image-card <?php echo $active_state === 'default' ? 'is-active' : ''; ?>" data-state="default" id="teksttv-sidebar-card-default" aria-pressed="<?php echo $active_state === 'default' ? 'true' : 'false'; ?>" @click.prevent="activateSidebarCardDefault()">
                            <span class="teksttv-image-card-label"><?php echo esc_html('Standaard'); ?></span>
                            <?php if ($fallback_url) : ?>
                                <img src="<?php echo esc_url($fallback_url); ?>" alt="" class="teksttv-image-card-thumb" width="100" height="68" />
                            <?php else : ?>
                                <span class="teksttv-image-card-icon"><span class="dashicons dashicons-format-image" aria-hidden="true"></span></span>
                            <?php endif; ?>
                        </button>
                        <button type="button" class="teksttv-image-card <?php echo $active_state === 'custom' ? 'is-active' : ''; ?>" data-state="custom" id="teksttv-sidebar-card-custom" aria-pressed="<?php echo $active_state === 'custom' ? 'true' : 'false'; ?>" @click.prevent="openSidebarCustom()">
                            <span class="teksttv-image-card-label"><?php echo esc_html('Eigen'); ?></span>
                            <?php if ($custom_url) : ?>
                                <img src="<?php echo esc_url($custom_url); ?>" alt="" class="teksttv-image-card-thumb" id="teksttv-sidebar-image-img" width="100" height="68" />
                            <?php else : ?>
                                <span class="teksttv-image-card-icon" id="teksttv-sidebar-image-placeholder"><span class="dashicons dashicons-upload" aria-hidden="true"></span></span>
                                <img src="" alt="" class="teksttv-image-card-thumb is-hidden" id="teksttv-sidebar-image-img" width="100" height="68" />
                            <?php endif; ?>
                        </button>
                        <button type="button" class="teksttv-image-card <?php echo $active_state === 'none' ? 'is-active' : ''; ?>" data-state="none" id="teksttv-sidebar-card-none" aria-pressed="<?php echo $active_state === 'none' ? 'true' : 'false'; ?>" @click.prevent="activateSidebarCardNone()">
                            <span class="teksttv-image-card-label"><?php echo esc_html('Geen'); ?></span>
                            <span class="teksttv-image-card-icon"><span class="dashicons dashicons-hidden" aria-hidden="true"></span></span>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (Helpers::has_feature('extra_images')) : ?>
                <!-- Extra images -->
                <div class="teksttv-meta-section teksttv-images-section">
                    <h4><?php echo esc_html('Extra afbeeldingen'); ?></h4>
                    <p class="description"><?php echo esc_html('Worden als aparte fullscreen image-slides getoond na de tekst.'); ?></p>
                    <div id="teksttv-images-list" class="teksttv-images-list" @click="onExtraImagesClick($event)">
                        <?php foreach ($images as $attachment_id) : ?>
                            <?php $thumb = wp_get_attachment_image_url($attachment_id, 'thumbnail'); ?>
                            <?php if ($thumb) : ?>
                            <div class="teksttv-image-item" data-id="<?php echo esc_attr($attachment_id); ?>">
                                <img src="<?php echo esc_url($thumb); ?>" alt="" width="90" height="90" loading="lazy" />
                                <input type="hidden" name="teksttv_images[]" value="<?php echo esc_attr($attachment_id); ?>" />
                                <button type="button" class="button-link teksttv-remove-image" aria-label="<?php echo esc_attr('Afbeelding verwijderen'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button teksttv-add-action" id="teksttv-add-images" @click="openExtraImages($event)"><?php echo esc_html('Afbeeldingen toevoegen'); ?></button>
                </div>
                <?php endif; ?>

                <?php if (Helpers::has_feature('scheduling')) : ?>
                <!-- Scheduling -->
                <div class="teksttv-meta-section teksttv-collapsible" x-data="{ planOpen: false }" :class="{ 'is-open': planOpen }">
                    <button type="button" class="teksttv-collapsible-toggle" aria-controls="teksttv-collapsible-planning" @click.prevent="planOpen = !planOpen" :aria-expanded="planOpen.toString()">
                        <span class="teksttv-section-label"><?php echo esc_html('Planning'); ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2 teksttv-collapsible-icon" aria-hidden="true"></span>
                    </button>
                    <div class="teksttv-collapsible-body" id="teksttv-collapsible-planning" x-show="planOpen" x-cloak>
                    <div class="teksttv-scheduling">
                        <div class="teksttv-scheduling-group">
                            <h4><?php echo esc_html('Periode'); ?></h4>
                            <div class="teksttv-dates-row">
                                <div class="teksttv-date-field">
                                    <label for="teksttv-date-start"><?php echo esc_html('Vanaf'); ?></label>
                                    <input type="date" name="teksttv_date_start" value="<?php echo esc_attr($date_start); ?>" id="teksttv-date-start" />
                                </div>
                                <div class="teksttv-date-field">
                                    <label for="teksttv-date-end"><?php echo esc_html('Tot en met'); ?></label>
                                    <input type="date" name="teksttv_date_end" value="<?php echo esc_attr($date_end); ?>" id="teksttv-date-end" @change="onDateEndChange()" />
                                    <button type="button" class="button-link teksttv-date-reset is-hidden" id="teksttv-date-end-reset" @click.prevent="resetDateEnd($event)"><?php echo esc_html('Standaard herstellen'); ?></button>
                                </div>
                            </div>
                        </div>
                        <div class="teksttv-scheduling-group">
                            <h4><?php echo esc_html('Weekdagen'); ?></h4>
                            <?php AdminPage::render_days_row('teksttv_days[]', $days); ?>
                            <p class="description"><?php echo esc_html('Bericht wordt alleen op geselecteerde dagen getoond.'); ?></p>
                        </div>
                    </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Preview sidebar -->
            <div class="teksttv-editor-preview" id="teksttv-preview-wrap">
                <div class="teksttv-preview-header">
                    <span class="teksttv-section-label"><?php echo esc_html('Preview'); ?></span>
                    <div class="teksttv-preview-nav" id="teksttv-preview-nav">
                        <button type="button" class="button button-small" id="teksttv-preview-prev" aria-label="<?php echo esc_attr('Vorige previewslide'); ?>" disabled @click.prevent="previewPrev()"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button>
                        <span class="teksttv-preview-counter" id="teksttv-preview-counter">1 / 1</span>
                        <button type="button" class="button button-small" id="teksttv-preview-next" aria-label="<?php echo esc_attr('Volgende previewslide'); ?>" disabled @click.prevent="previewNext()"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>
                    </div>
                </div>
                <?php if ($preview_url) : ?>
                    <div class="teksttv-preview-container">
                        <iframe id="teksttv-preview-iframe" class="teksttv-preview-iframe" sandbox="allow-scripts allow-same-origin"></iframe>
                        <button type="button" class="teksttv-preview-enlarge-btn" id="teksttv-preview-enlarge" aria-label="<?php echo esc_attr('Preview vergroten'); ?>" title="<?php echo esc_attr('Preview vergroten'); ?>" @click.prevent="openPreviewOverlay()"><span class="dashicons dashicons-editor-expand" aria-hidden="true"></span></button>
                    </div>
                    <div class="teksttv-preview-thumbs" id="teksttv-preview-thumbs" @click="onPreviewThumbClick($event)">
                        <!-- Filled by JS: mini slide thumbnails -->
                    </div>
                <?php else : ?>
                    <div class="teksttv-no-preview">
                        <?php echo wp_kses(sprintf('Stel een preview-URL in bij <a href="%s">Tekst TV &rarr; Instellingen</a> om de live preview te activeren.', esc_url(admin_url('admin.php?page=teksttv-settings'))), ['a' => ['href' => []]]); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
