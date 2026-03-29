import { initTomSelectIn } from './utils';

/** Loop configuration page + Campaigns page: sortable blocks, media pickers. */
export function initLoopPage(): void {
    const $ = jQuery;
    // Works for both #teksttv-blocks (loop) and #teksttv-campaigns (campaigns page)
    const $blocks = $('#teksttv-blocks, #teksttv-campaigns').first();
    if (!$blocks.length) return;

    $blocks.sortable({
        handle: '.teksttv-block-handle',
        placeholder: 'teksttv-block ui-sortable-placeholder',
        tolerance: 'pointer',
        update: () => reindexBlocks(),
    });

    // =========================================================================
    // Split button: block type dropdown
    // =========================================================================

    let selectedBlockType = 'articles';

    $('#teksttv-add-block-toggle').on('click', (e) => {
        e.stopPropagation();
        $('#teksttv-add-block-menu').toggleClass('is-open');
    });

    $('#teksttv-add-block-menu').on('click', 'button', function () {
        selectedBlockType = $(this).data('type') as string;
        const label = $(this).text().trim();
        $('#teksttv-add-block-label').text(label);
        $('#teksttv-add-block-menu button').removeClass('is-active');
        $(this).addClass('is-active');
        $('#teksttv-add-block-menu').removeClass('is-open');
    });

    $(document).on('click', () => {
        $('.teksttv-split-button-menu').removeClass('is-open');
    });

    // =========================================================================
    // Add block (loop page)
    // =========================================================================

    $('#teksttv-add-block-btn').on('click', () => {
        $('#teksttv-empty-state').remove();

        const type = selectedBlockType;
        const templateHtml = $(`#tmpl-teksttv-block-${type}`).html();
        if (!templateHtml) return;
        const index = $blocks.children('.teksttv-block').length;
        const rendered = templateHtml.replace(/__INDEX__/g, String(index));
        $blocks.append(rendered);
        const $newBlock = $blocks.children('.teksttv-block').last();
        $blocks.sortable('refresh');
        updateBlockSummaries();

        $newBlock.find('.teksttv-block-body').show();
        $newBlock.addClass('is-expanded');
        initTomSelectIn($newBlock[0]);
    });

    // =========================================================================
    // Add campaign (campaigns page)
    // =========================================================================

    $('#teksttv-add-campaign').on('click', () => {
        $('#teksttv-empty-state').remove();
        const templateHtml = $('#tmpl-teksttv-campaign').html();
        const index = $blocks.children('.teksttv-block').length;
        const rendered = templateHtml.replace(/__INDEX__/g, String(index));
        $blocks.append(rendered);
        const $newBlock = $blocks.children('.teksttv-block').last();
        $blocks.sortable('refresh');
        $newBlock.find('.teksttv-block-body').show();
        $newBlock.addClass('is-expanded');
    });

    // =========================================================================
    // Block actions: remove, collapse, field changes
    // =========================================================================

    $blocks.on('click', '.teksttv-remove-block', function (e) {
        e.stopPropagation();
        $(this)
            .closest('.teksttv-block')
            .slideUp(200, function () {
                $(this).remove();
                reindexBlocks();
            });
    });

    $blocks.on('click', '.teksttv-block-header', function (e) {
        if ($(e.target).closest('.teksttv-remove-block').length) return;
        const $block = $(this).closest('.teksttv-block');
        $block.toggleClass('is-expanded');
        $block.find('.teksttv-block-body').slideToggle(150);
    });

    $blocks.on('change input', '.teksttv-block-body input, .teksttv-block-body select', () => {
        updateBlockSummaries();
    });

    // =========================================================================
    // Campaign slide media picker
    // =========================================================================

    $blocks.on('click', '.teksttv-campaign-add-slides', function (e) {
        e.preventDefault();
        const $section = $(this).closest('.teksttv-campaign-slides-section');
        const $list = $section.find('.teksttv-campaign-slides');
        const baseName = $list.data('name') as string;
        const frame = (wp as any).media({ multiple: true, library: { type: 'image' } });
        frame.on('select', () => {
            const attachments = frame.state().get('selection').toJSON();
            attachments.forEach((att: any) => {
                const thumbUrl = att.sizes?.thumbnail?.url ?? att.url;
                const html =
                    `<div class="teksttv-image-item" data-id="${att.id}">` +
                    `<img src="${thumbUrl}" alt="" />` +
                    `<input type="hidden" name="${baseName}" value="${att.id}" />` +
                    '<button type="button" class="button-link teksttv-remove-image"><span class="dashicons dashicons-no-alt"></span></button>' +
                    '</div>';
                $list.append(html);
            });
        });
        frame.open();
    });

    // =========================================================================
    // Image/transition block media picker (scoped to closest field)
    // =========================================================================

    $blocks.on('click', '.teksttv-block-image-select', function (e) {
        e.preventDefault();
        const $field = $(this).closest('.teksttv-block-field, .teksttv-block-image-fields');
        const frame = (wp as any).media({ multiple: false, library: { type: 'image' } });
        frame.on('select', () => {
            const att = frame.state().get('selection').first().toJSON();
            const url = att.sizes?.medium?.url ?? att.url;
            $field.find('.teksttv-block-image-id').val(att.id);
            $field.find('.teksttv-block-image-thumb').attr('src', url);
            $field.find('.teksttv-block-image-preview').removeClass('is-hidden');
            $field.find('.teksttv-block-image-remove').removeClass('is-hidden');
            updateBlockSummaries();
        });
        frame.open();
    });

    $blocks.on('click', '.teksttv-block-image-remove', function () {
        const $field = $(this).closest('.teksttv-block-field, .teksttv-block-image-fields');
        $field.find('.teksttv-block-image-id').val('');
        $field.find('.teksttv-block-image-preview').addClass('is-hidden');
        $(this).addClass('is-hidden');
        updateBlockSummaries();
    });

    // =========================================================================
    // Scheduling toggle
    // =========================================================================

    $blocks.on('change', '.teksttv-scheduling-checkbox', function () {
        const $scheduling = $(this)
            .closest('.teksttv-block-scheduling-toggle')
            .next('.teksttv-block-fields--scheduling');
        if ($(this).is(':checked')) {
            $scheduling.slideDown(150);
        } else {
            $scheduling.slideUp(150);
            $scheduling.find('input[type="date"]').val('');
            $scheduling.find('input[type="checkbox"]').prop('checked', true);
        }
    });

    // =========================================================================
    // Expand / collapse all
    // =========================================================================

    $('#teksttv-expand-all').on('click', () => {
        $blocks.children('.teksttv-block').each(function () {
            $(this).addClass('is-expanded').find('.teksttv-block-body').slideDown(150);
        });
    });

    $('#teksttv-collapse-all').on('click', () => {
        $blocks.children('.teksttv-block').each(function () {
            $(this).removeClass('is-expanded').find('.teksttv-block-body').slideUp(150);
        });
    });

    // =========================================================================
    // Ticker items
    // =========================================================================

    const $ticker = $('#teksttv-ticker');
    if ($ticker.length) {
        $ticker.sortable({
            handle: '.teksttv-block-handle',
            placeholder: 'teksttv-block ui-sortable-placeholder',
            tolerance: 'pointer',
            update: () => reindexTicker(),
        });

        // Collapse, expand, remove — reuse existing block handlers (they delegate on $blocks)
        // but ticker has its own container, so add handlers here too
        $ticker.on('click', '.teksttv-block-header', function (e) {
            if ($(e.target).closest('.teksttv-remove-block').length) return;
            const $block = $(this).closest('.teksttv-block');
            $block.toggleClass('is-expanded');
            $block.find('.teksttv-block-body').slideToggle(150);
        });

        $ticker.on('click', '.teksttv-remove-block', function (e) {
            e.stopPropagation();
            $(this).closest('.teksttv-block').slideUp(200, function () {
                $(this).remove();
                reindexTicker();
            });
        });

        $ticker.on('change', '.teksttv-scheduling-checkbox', function () {
            const $scheduling = $(this).closest('.teksttv-block-scheduling-toggle').next('.teksttv-block-fields--scheduling');
            if ($(this).is(':checked')) {
                $scheduling.slideDown(150);
            } else {
                $scheduling.slideUp(150);
                $scheduling.find('input[type="date"]').val('');
                $scheduling.find('input[type="checkbox"]').prop('checked', true);
            }
        });

        // Ticker split button
        let selectedTickerType = 'ticker_text';

        $('#teksttv-add-ticker-toggle').on('click', (e) => {
            e.stopPropagation();
            $('#teksttv-add-ticker-menu').toggleClass('is-open');
        });

        $('#teksttv-add-ticker-menu').on('click', 'button', function () {
            selectedTickerType = $(this).data('type') as string;
            const label = $(this).text().trim();
            $('#teksttv-add-ticker-label').text(label);
            $('#teksttv-add-ticker-menu button').removeClass('is-active');
            $(this).addClass('is-active');
            $('#teksttv-add-ticker-menu').removeClass('is-open');
        });

        $('#teksttv-add-ticker-btn').on('click', () => {
            const type = selectedTickerType;
            const templateHtml = $(`#tmpl-teksttv-ticker-${type}`).html();
            if (!templateHtml) return;
            const index = $ticker.children('.teksttv-block').length;
            const rendered = templateHtml.replace(/__TINDEX__/g, String(index));
            $ticker.append(rendered);
            const $newBlock = $ticker.children('.teksttv-block').last();
            $ticker.sortable('refresh');
            $newBlock.find('.teksttv-block-body').show();
            $newBlock.addClass('is-expanded');
            initTomSelectIn($newBlock[0]);
            $newBlock.find('input[type="text"]').first().trigger('focus');
        });

        function reindexTicker(): void {
            $ticker.children('.teksttv-block').each(function (i) {
                $(this)
                    .find('input, select')
                    .each(function () {
                        const name = $(this).attr('name');
                        if (name) {
                            $(this).attr('name', name.replace(/teksttv_ticker\[\d+\]/, `teksttv_ticker[${i}]`));
                        }
                    });
            });
        }

        // Collapse ticker items initially
        $ticker.children('.teksttv-block').each(function () {
            $(this).find('.teksttv-block-body').hide();
        });
    }

    // Generate summaries and collapse all blocks initially
    updateBlockSummaries();
    $blocks.children('.teksttv-block').each(function () {
        $(this).find('.teksttv-block-body').hide();
    });

    // =========================================================================
    // Helpers
    // =========================================================================

    function reindexBlocks(): void {
        $blocks.children('.teksttv-block').each(function (i) {
            $(this)
                .find('input, select')
                .each(function () {
                    const name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/(teksttv_(?:blocks|campaigns))\[\d+\]/, `$1[${i}]`));
                    }
                });
        });
    }

    function getSchedulingSuffix($block: JQuery): string {
        const $dates = $block.find('.teksttv-block-fields--scheduling input[type="date"]');
        if (!$dates.length) return '';

        const ds = $dates.first().val() as string;
        const de = $dates.last().val() as string;
        if (ds || de) {
            return ` · ${ds || '...'} – ${de || '...'}`;
        }
        return '';
    }

    function updateBlockSummaries(): void {
        $blocks.children('.teksttv-block').each(function () {
            const $block = $(this);
            const type = $block.data('type') as string;
            const scheduling = getSchedulingSuffix($block);
            let summary = '';

            if (type === 'image') {
                const imageId = $block.find('.teksttv-block-image-id').first().val() as string;
                summary = imageId && imageId !== '0' ? 'Afbeelding' : 'Geen afbeelding';
            } else if (type === 'commercial') {
                const groups: string[] = [];
                $block.find('select option:selected').each(function () {
                    if ($(this).val()) groups.push($(this).text());
                });
                summary = groups.length ? `Groep ${groups.join(', ')}` : 'Geen groep';
            } else if (type === 'weather') {
                summary = ($block.find('input[type="text"]').first().val() as string) || 'Geen locatie';
            } else if (type === 'campaign') {
                summary = ($block.find('input[type="text"]').first().val() as string) || 'Naamloze campagne';
            } else {
                // Articles
                const count = ($block.find('input[type="number"]').first().val() as string) || '?';
                const parts = [`${count}x`];

                $block.find('select').each(function () {
                    const $sel = $(this);
                    const names: string[] = [];
                    $sel.find('option:selected').each(function () {
                        if ($(this).val()) names.push($(this).text());
                    });
                    if (names.length) parts.push(names.join(', '));
                });

                if (parts.length === 1) parts.push('alle');
                summary = parts.join(' · ');
            }

            $block.find('.teksttv-block-summary').text(summary + scheduling);
        });
    }
}
