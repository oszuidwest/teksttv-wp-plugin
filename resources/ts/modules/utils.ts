import { fadeOutRemove, siblingFocusTarget } from './dom';
import type { Slide, WPMediaAttachment } from './types';

/** Escape a string for safe insertion into an HTML attribute. */
export function escAttr(value: string | number): string {
    return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
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
    const url = new URL(baseUrl, window.location.href);
    url.searchParams.set('data', encodeSlideData(slide));
    return url.href;
}

/**
 * Split editor HTML on page separators. Uses the same separator regex as PHP
 * ArticlesLoopBlock::split_pages, but unlike PHP it keeps empty/untrimmed
 * segments — callers filter or count as needed. When disabled, the preview
 * must match the server and keep the content on one page.
 */
export function splitPages(html: string, enabled = true): string[] {
    if (!enabled) return [html];
    return html.split(/<p[^>]*>\s*-{3,}\s*<\/p>|(?:^|\r?\n)[ \t]*-{3,}[ \t]*(?=\r?\n|$)/i);
}

/** Debounce `fn`: each call restarts the timer; only the last call within `ms` runs. */
export function debounce(fn: () => void, ms: number): () => void {
    let timer: number | undefined;
    return () => {
        clearTimeout(timer);
        timer = window.setTimeout(fn, ms);
    };
}

/** Replace HTML tags with spaces (callers trim/collapse as needed). */
export function stripTags(html: string): string {
    return html.replace(/<[^>]+>/g, ' ');
}

/**
 * Remove the image item owning a remove button. Disable its form controls
 * before fading so an immediate save cannot submit an item that appears gone.
 */
export function removeImageItem(button: Element, onRemoved?: () => void): void {
    const item = button.closest('.teksttv-image-item');
    if (!(item instanceof HTMLElement)) return;
    const focusTarget = siblingFocusTarget(
        item,
        '.teksttv-remove-image',
        item.closest('.teksttv-campaign-slides-section')?.querySelector<HTMLElement>('.teksttv-campaign-add-slides') ??
            document.querySelector<HTMLElement>('#teksttv-add-images'),
    );

    item.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>(
        'input, select, textarea',
    ).forEach((control) => {
        control.disabled = true;
    });
    fadeOutRemove(item, 150, () => {
        onRemoved?.();
        focusTarget?.focus();
    });
}

/**
 * Insert picked attachments into an image list, then focus the first new
 * item's remove button. The focus is deferred because wp.media returns
 * focus to its opener when the modal closes.
 */
export function appendImageItems(list: Element, attachments: WPMediaAttachment[], inputName: string): void {
    const firstNewIndex = list.children.length;
    for (const att of attachments) {
        list.insertAdjacentHTML('beforeend', imageItemHtml(att, inputName));
    }
    window.setTimeout(() => {
        list.children[firstNewIndex]?.querySelector<HTMLButtonElement>('.teksttv-remove-image')?.focus();
    });
}

/** HTML fragment for a removable image item in an image list. */
export function imageItemHtml(att: WPMediaAttachment, inputName: string): string {
    const thumbUrl = att.sizes?.thumbnail?.url ?? att.url;
    return (
        `<div class="teksttv-image-item" data-id="${escAttr(att.id)}">` +
        `<img src="${escAttr(thumbUrl)}" alt="" />` +
        `<input type="hidden" name="${escAttr(inputName)}" value="${escAttr(att.id)}" />` +
        '<button type="button" class="button-link teksttv-remove-image" aria-label="Afbeelding verwijderen"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>' +
        '</div>'
    );
}
