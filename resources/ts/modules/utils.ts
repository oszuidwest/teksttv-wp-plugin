import { fadeOutRemove } from './dom';
import type { Slide, WPMediaAttachment } from './types';

/** Escape a string for safe insertion into an HTML attribute. */
function escAttr(value: string | number): string {
    return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/** Debounce a zero-argument function: only the last call within `ms` runs. */
export function debounce(fn: () => void, ms: number): () => void {
    let timer: ReturnType<typeof setTimeout> | undefined;
    return () => {
        clearTimeout(timer);
        timer = window.setTimeout(fn, ms);
    };
}

/** Encode a slide object to a base64 string for the preview URL. */
function encodeSlideData(slide: Slide): string {
    const json = JSON.stringify(slide);
    const bytes = new TextEncoder().encode(json);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
}

/** Build the preview iframe URL for a single slide. */
export function previewSlideUrl(baseUrl: string, slide: Slide): string {
    return `${baseUrl}?data=${encodeURIComponent(encodeSlideData(slide))}`;
}

/**
 * Split editor HTML on page separators. Uses the same separator regex as PHP
 * ArticlesLoopBlock::split_pages, but unlike PHP it keeps empty/untrimmed
 * segments (callers filter or count as needed). Pass `enabled` from the
 * PHP-owned `pageSeparator` config flag so the preview matches what PHP
 * builds when the feature is disabled: one page.
 */
export function splitPages(html: string, enabled = true): string[] {
    if (!enabled) return [html];
    return html.split(/<p[^>]*>\s*-{3,}\s*<\/p>|\n*-{3,}\n*/i);
}

/** Replace HTML tags with spaces (callers trim/collapse as needed). */
export function stripTags(html: string): string {
    return html.replace(/<[^>]+>/g, ' ');
}

/**
 * Remove the `.teksttv-image-item` owning a `.teksttv-remove-image` button.
 * The counterpart of `imageItemHtml`: every list built from that markup
 * removes items this way. Disable form controls before the fade so a save
 * during the animation cannot submit the removed image.
 */
export function removeImageItem(button: Element, onRemoved?: () => void): void {
    const item = button.closest('.teksttv-image-item');
    if (!(item instanceof HTMLElement)) return;

    item.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>(
        'input, select, textarea',
    ).forEach((control) => {
        control.disabled = true;
    });
    fadeOutRemove(item, 150, onRemoved);
}

/** HTML fragment for a removable image item in an image list. */
export function imageItemHtml(att: WPMediaAttachment, inputName: string): string {
    const thumbUrl = att.sizes?.thumbnail?.url ?? att.url;
    return (
        `<div class="teksttv-image-item" data-id="${escAttr(att.id)}">` +
        `<img src="${escAttr(thumbUrl)}" alt="" />` +
        `<input type="hidden" name="${escAttr(inputName)}" value="${escAttr(att.id)}" />` +
        '<button type="button" class="button-link teksttv-remove-image"><span class="dashicons dashicons-no-alt"></span></button>' +
        '</div>'
    );
}

/** Initialize TomSelect on elements within a container. */
export function initTomSelectIn(container: Element | Document = document): void {
    if (typeof TomSelect === 'undefined') return;

    container.querySelectorAll<HTMLSelectElement>('.teksttv-tomselect').forEach((el) => {
        if ((el as unknown as { tomselect?: unknown }).tomselect) return;
        new TomSelect(el, {
            plugins: ['remove_button'],
            placeholder: el.dataset.placeholder || 'Filter...',
            allowEmptyOption: true,
        });
    });
}
