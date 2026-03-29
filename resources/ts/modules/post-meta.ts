import type { ImageData, Slide, TextSlide, TeksttvPostConfig, WPMediaAttachment } from './types';
import { encodeSlideData } from './utils';

function updateThumbnails($thumbs: JQuery, slides: Slide[], activeIndex: number, baseUrl: string): void {
    const thumbCount = $thumbs.children().length;
    const needsRebuild = thumbCount !== slides.length;

    if (needsRebuild) {
        $thumbs.empty();
        slides.forEach((slide, idx) => {
            const cls = idx === activeIndex ? 'teksttv-preview-thumb is-active' : 'teksttv-preview-thumb';
            const src = `${baseUrl}?data=${encodeURIComponent(encodeSlideData(slide))}`;
            const html =
                `<div class="${cls}" data-index="${idx}">` +
                `<iframe src="${src}" sandbox="allow-scripts allow-same-origin" tabindex="-1"></iframe>` +
                `<span class="teksttv-preview-thumb-number">${idx + 1}</span>` +
                '</div>';
            $thumbs.append(html);
        });
    } else {
        $thumbs.children().each(function (idx) {
            const $el = jQuery(this);
            if (!slides[idx]) return;
            const newSrc = `${baseUrl}?data=${encodeURIComponent(encodeSlideData(slides[idx]))}`;
            const $iframeEl = $el.find('iframe');
            $el.toggleClass('is-active', idx === activeIndex);
            if ($iframeEl.attr('src') !== newSrc) {
                $iframeEl.attr('src', newSrc);
            }
        });
    }
}

/** Post meta box: toggle, preview, sidebar image, scheduling, word count. */
export function initPostMeta(): void {
    const $ = jQuery;
    const $active = $('#teksttv-active');
    const $fields = $('#teksttv-fields');
    const $iframe = $('#teksttv-preview-iframe');
    const $status = $('#teksttv-toggle-status');

    if (!$active.length) return;

    const config: TeksttvPostConfig | undefined = typeof teksttvPost !== 'undefined' ? teksttvPost : undefined;

    // =========================================================================
    // Toggle fields visibility
    // =========================================================================

    function toggleFields(): void {
        const isActive = $active.is(':checked');
        if (isActive) {
            $fields.slideDown(200);
            $status.text('Actief').addClass('is-active');
        } else {
            $fields.slideUp(200);
            $status.text('Inactief').removeClass('is-active');
        }
    }

    if ($active.is(':checked')) {
        $fields.show();
    } else {
        $fields.hide();
    }
    $active.on('change', toggleFields);

    // =========================================================================
    // Collapsible sections
    // =========================================================================

    $('.teksttv-collapsible-body').hide().removeClass('is-hidden');
    $('.teksttv-collapsible-toggle').on('click', function () {
        const $section = $(this).closest('.teksttv-collapsible');
        $section.toggleClass('is-open');
        $section.find('.teksttv-collapsible-body').first().slideToggle(150);
    });

    // =========================================================================
    // Date reset button
    // =========================================================================

    const defaultEndDate = config?.defaultEndDate ?? '';
    const $dateEnd = $('#teksttv-date-end');
    const $dateReset = $('#teksttv-date-end-reset');

    function checkDateReset(): void {
        if (defaultEndDate && $dateEnd.val() !== defaultEndDate) {
            $dateReset.removeClass('is-hidden');
        } else {
            $dateReset.addClass('is-hidden');
        }
    }

    $dateEnd.on('change', checkDateReset);
    checkDateReset();

    $dateReset.on('click', function () {
        if (defaultEndDate) {
            $dateEnd.val(defaultEndDate);
            $(this).addClass('is-hidden');
        }
    });

    // =========================================================================
    // Extra images: media picker + sortable
    // =========================================================================

    let mediaFrame: any;
    $('#teksttv-add-images').on('click', (e) => {
        e.preventDefault();
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }
        mediaFrame = (wp as any).media({
            title: 'Afbeeldingen selecteren',
            button: { text: 'Toevoegen' },
            multiple: true,
            library: { type: 'image' },
        });
        mediaFrame.on('select', () => {
            const attachments: WPMediaAttachment[] = mediaFrame.state().get('selection').toJSON();
            const $list = $('#teksttv-images-list');
            for (const att of attachments) {
                const thumbUrl = att.sizes?.thumbnail?.url ?? att.url;
                const html =
                    `<div class="teksttv-image-item" data-id="${att.id}">` +
                    `<img src="${thumbUrl}" alt="" />` +
                    `<input type="hidden" name="teksttv_images[]" value="${att.id}" />` +
                    '<button type="button" class="button-link teksttv-remove-image"><span class="dashicons dashicons-no-alt"></span></button>' +
                    '</div>';
                $list.append(html);
            }
        });
        mediaFrame.open();
    });

    $('#teksttv-images-list').sortable({
        tolerance: 'pointer',
        cursor: 'move',
        placeholder: 'teksttv-image-item ui-sortable-placeholder',
    });

    $(document).on('click', '.teksttv-remove-image', function () {
        $(this)
            .closest('.teksttv-image-item')
            .fadeOut(150, function () {
                $(this).remove();
            });
    });

    // =========================================================================
    // Sidebar image card selector (3 states: default, custom, none)
    // =========================================================================

    let sidebarFrame: any;
    let customImageData: ImageData | null = config?.customImage ? (config.customImage as ImageData) : null;

    function activateSidebarCard(state: string): void {
        $('.teksttv-image-card').removeClass('is-active');
        $(`.teksttv-image-card[data-state="${state}"]`).addClass('is-active');
        $('.teksttv-image-cards').data('active', state);

        if (state === 'none') {
            $('#teksttv-sidebar-image-id').val('0');
        } else if (state === 'default') {
            $('#teksttv-sidebar-image-id').val('');
        }
        updatePreview();
    }

    $('#teksttv-sidebar-card-default').on('click', () => activateSidebarCard('default'));
    $('#teksttv-sidebar-card-none').on('click', () => activateSidebarCard('none'));

    $('#teksttv-sidebar-card-custom').on('click', (e) => {
        e.preventDefault();
        if (sidebarFrame) {
            sidebarFrame.open();
            return;
        }
        sidebarFrame = (wp as any).media({ multiple: false, library: { type: 'image' } });
        sidebarFrame.on('select', () => {
            const att: WPMediaAttachment = sidebarFrame.state().get('selection').first().toJSON();
            const url = att.sizes?.medium?.url ?? att.url;
            $('#teksttv-sidebar-image-id').val(att.id);
            $('#teksttv-sidebar-image-img').attr('src', url).removeClass('is-hidden');
            $('#teksttv-sidebar-image-placeholder').addClass('is-hidden');

            if (config?.imageDataUrl) {
                $.ajax({
                    url: config.imageDataUrl,
                    data: { id: att.id },
                    beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', config.restNonce),
                    success: (data: ImageData) => {
                        customImageData = data;
                        activateSidebarCard('custom');
                    },
                    error: () => {
                        const fullUrl = att.sizes?.large?.url ?? att.url;
                        customImageData = { url: fullUrl };
                        if (att.caption) customImageData.caption = att.caption;
                        activateSidebarCard('custom');
                    },
                });
            } else {
                customImageData = { url: att.sizes?.large?.url ?? att.url };
                activateSidebarCard('custom');
            }
        });
        sidebarFrame.open();
    });

    // =========================================================================
    // Live preview with slide navigation
    // =========================================================================

    const previewUrl = config?.previewUrl ?? '';
    let debounceTimer: ReturnType<typeof setTimeout>;
    let currentSlideIndex = 0;
    let slides: Slide[] = [];

    function getEditorContent(): string {
        const editor = typeof tinymce !== 'undefined' ? tinymce?.get('teksttv_content') : null;
        if (editor && !editor.isHidden()) {
            return editor.getContent();
        }
        const $textarea = $('#teksttv_content');
        return $textarea.length ? ($textarea.val() as string) : '';
    }

    function getSlides(): Slide[] {
        const customTitle = (($('#teksttv-title').val() as string) || '').trim();
        const postTitle = (
            ($('#title').val() as string) ||
            ($('input[name="post_title"]').val() as string) ||
            ''
        ).trim();
        const placeholderTitle = $('#teksttv-title').attr('placeholder') || '';
        const title = customTitle || postTitle || placeholderTitle;
        const content = getEditorContent();
        const result: Slide[] = [];

        // Split on --- separators
        const pages = content.split(/<p[^>]*>\s*-{3,}\s*<\/p>/i);
        const expandedPages: string[] = [];
        for (const page of pages) {
            for (const sp of page.split(/\n*-{3,}\n*/)) {
                expandedPages.push(sp);
            }
        }

        // Get sidebar image
        let sidebarImg: ImageData | null = null;
        const activeState = ($('.teksttv-image-cards').data('active') as string) || 'default';
        if (activeState === 'custom' && customImageData) {
            sidebarImg = customImageData;
        } else if (activeState === 'default') {
            const fallback = config?.fallbackImage;
            sidebarImg = fallback && typeof fallback === 'object' ? fallback : null;
        }

        for (const page of expandedPages) {
            const trimmed = page.trim();
            if (!trimmed) continue;
            const slide: TextSlide = {
                type: 'text' as const,
                duration: 20000,
                title,
                body: trimmed,
            };
            if (sidebarImg) slide.image = sidebarImg;
            result.push(slide);
        }

        // Extra image slides
        $('#teksttv-images-list .teksttv-image-item').each(function () {
            const $img = $(this).find('img');
            if ($img.length) {
                result.push({
                    type: 'image',
                    duration: 7000,
                    url: ($img.attr('src') ?? '').replace(/-\d+x\d+\./, '.'),
                });
            }
        });

        return result;
    }

    function updatePreview(): void {
        if (!previewUrl || !$iframe.length) return;

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            slides = getSlides();
            if (currentSlideIndex >= slides.length) currentSlideIndex = slides.length - 1;
            if (currentSlideIndex < 0) currentSlideIndex = 0;
            updatePreviewNav();

            const $container = $iframe.closest('.teksttv-preview-container');
            if (slides.length === 0) {
                $iframe.attr('src', 'about:blank');
                $container.removeClass('is-loading').addClass('is-empty');
                return;
            }

            $container.removeClass('is-empty').addClass('is-loading');
            $iframe.attr('src', `${previewUrl}?data=${encodeURIComponent(encodeSlideData(slides[currentSlideIndex]))}`);
            $iframe.off('load.teksttv').on('load.teksttv', () => $container.removeClass('is-loading'));
        }, 400);
    }

    function updatePreviewNav(): void {
        const total = slides.length;
        const current = total > 0 ? currentSlideIndex + 1 : 0;

        $('#teksttv-preview-counter').text(`${current} / ${total}`);
        $('#teksttv-preview-prev').prop('disabled', currentSlideIndex <= 0);
        $('#teksttv-preview-next').prop('disabled', currentSlideIndex >= total - 1);

        const $thumbs = $('#teksttv-preview-thumbs');
        if ($thumbs.length && previewUrl) {
            updateThumbnails($thumbs, slides, currentSlideIndex, previewUrl);
        }
    }

    // Thumbnail clicks
    $(document).on('click', '.teksttv-preview-thumb', function () {
        const idx = parseInt($(this).data('index') as string, 10);
        if (!Number.isNaN(idx) && idx >= 0 && idx < slides.length) {
            currentSlideIndex = idx;
            updatePreview();
        }
    });

    // Enlarge preview overlay
    $('#teksttv-preview-enlarge').on('click', () => {
        slides = getSlides();
        if (!slides.length) return;
        let overlayIndex = currentSlideIndex;

        const getOverlaySrc = (idx: number) => `${previewUrl}?data=${encodeURIComponent(encodeSlideData(slides[idx]))}`;

        const $overlay = $(
            '<div class="teksttv-preview-overlay">' +
                '<div class="teksttv-overlay-header">' +
                '<button type="button" class="teksttv-overlay-nav-btn teksttv-overlay-prev" title="Vorige"><span class="dashicons dashicons-arrow-left-alt2"></span></button>' +
                '<span class="teksttv-overlay-counter"></span>' +
                '<button type="button" class="teksttv-overlay-nav-btn teksttv-overlay-next" title="Volgende"><span class="dashicons dashicons-arrow-right-alt2"></span></button>' +
                '<button type="button" class="teksttv-preview-overlay-close" title="Sluiten">&times;</button>' +
                '</div>' +
                `<iframe src="${getOverlaySrc(overlayIndex)}" sandbox="allow-scripts allow-same-origin"></iframe>` +
                '</div>',
        );

        function updateOverlayNav(): void {
            $overlay.find('.teksttv-overlay-counter').text(`${overlayIndex + 1} / ${slides.length}`);
            $overlay.find('.teksttv-overlay-prev').prop('disabled', overlayIndex <= 0);
            $overlay.find('.teksttv-overlay-next').prop('disabled', overlayIndex >= slides.length - 1);
        }

        updateOverlayNav();
        $('body').append($overlay);

        $overlay.find('.teksttv-overlay-prev').on('click', () => {
            if (overlayIndex > 0) {
                overlayIndex--;
                $overlay.find('iframe').attr('src', getOverlaySrc(overlayIndex));
                updateOverlayNav();
            }
        });

        $overlay.find('.teksttv-overlay-next').on('click', () => {
            if (overlayIndex < slides.length - 1) {
                overlayIndex++;
                $overlay.find('iframe').attr('src', getOverlaySrc(overlayIndex));
                updateOverlayNav();
            }
        });

        $overlay.on('click', (e) => {
            if ($(e.target).is('.teksttv-preview-overlay, .teksttv-preview-overlay-close')) {
                $overlay.remove();
                $(document).off('keydown.teksttv-overlay');
            }
        });

        $(document).on('keydown.teksttv-overlay', (e) => {
            if (e.key === 'Escape') {
                $overlay.remove();
                $(document).off('keydown.teksttv-overlay');
            } else if (e.key === 'ArrowLeft') {
                $overlay.find('.teksttv-overlay-prev').trigger('click');
            } else if (e.key === 'ArrowRight') {
                $overlay.find('.teksttv-overlay-next').trigger('click');
            }
        });
    });

    // Navigation buttons
    $('#teksttv-preview-prev').on('click', () => {
        if (currentSlideIndex > 0) {
            currentSlideIndex--;
            updatePreview();
        }
    });

    $('#teksttv-preview-next').on('click', () => {
        if (currentSlideIndex < slides.length - 1) {
            currentSlideIndex++;
            updatePreview();
        }
    });

    // =========================================================================
    // Word count
    // =========================================================================

    function updateWordCount(): void {
        const content = getEditorContent();
        const $wc = $('#teksttv-wordcount');
        if (!$wc.length) return;

        const text = content
            .replace(/<[^>]+>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
        const pageCount = content.split(/<p[^>]*>\s*-{3,}\s*<\/p>|\n*-{3,}\n*/i).length;
        const totalWords = text ? text.split(/\s+/).length : 0;

        const parts = [`<span>${totalWords} woorden</span>`];
        if (pageCount > 1) {
            parts.push(`<span>${pageCount} slides</span>`);
        }
        $wc.html(parts.join(' · '));
    }

    // =========================================================================
    // TinyMCE + textarea change listeners
    // =========================================================================

    if (typeof tinymce !== 'undefined') {
        tinymce.on('AddEditor', (e) => {
            if (e.editor.id === 'teksttv_content') {
                e.editor.on('input change keyup', () => {
                    updatePreview();
                    updateWordCount();
                });
                e.editor.on('SetContent', () => {
                    updatePreview();
                    updateWordCount();
                });
            }
        });
    }

    $(document).on('input', '#teksttv_content', () => {
        updatePreview();
        updateWordCount();
    });

    $('#title, #teksttv-title').on('input', updatePreview);

    setTimeout(() => {
        updatePreview();
        updateWordCount();
    }, 500);
}
