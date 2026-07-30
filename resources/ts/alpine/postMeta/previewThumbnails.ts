import type { Slide } from '../../modules/types';
import { previewSlideUrl } from '../../modules/utils';

export function updatePreviewThumbnails(
    thumbs: HTMLElement,
    slides: Slide[],
    activeIndex: number,
    baseUrl: string,
): void {
    const thumbCount = thumbs.children.length;
    const needsRebuild = thumbCount !== slides.length;

    if (needsRebuild) {
        thumbs.replaceChildren();
        slides.forEach((slide, idx) => {
            const cls = idx === activeIndex ? 'teksttv-preview-thumb is-active' : 'teksttv-preview-thumb';
            const src = previewSlideUrl(baseUrl, slide);
            const html =
                '<div class="teksttv-preview-thumb-shell">' +
                `<iframe src="${src}" sandbox="allow-scripts allow-same-origin" tabindex="-1"></iframe>` +
                `<button type="button" class="${cls}" data-index="${idx}" aria-label="Toon previewslide ${idx + 1}" aria-pressed="${idx === activeIndex}">` +
                `<span class="teksttv-preview-thumb-number" aria-hidden="true">${idx + 1}</span>` +
                '</button>' +
                '</div>';
            thumbs.insertAdjacentHTML('beforeend', html);
        });
    } else {
        Array.from(thumbs.children).forEach((child, idx) => {
            const el = child instanceof HTMLElement ? child : null;
            if (!el || !slides[idx]) return;
            const newSrc = previewSlideUrl(baseUrl, slides[idx]);
            const iframeEl = el.querySelector<HTMLIFrameElement>('iframe');
            const button = el.querySelector<HTMLButtonElement>('.teksttv-preview-thumb');
            button?.classList.toggle('is-active', idx === activeIndex);
            button?.setAttribute('aria-pressed', String(idx === activeIndex));
            if (iframeEl && iframeEl.getAttribute('src') !== newSrc) {
                iframeEl.setAttribute('src', newSrc);
            }
        });
    }
}
