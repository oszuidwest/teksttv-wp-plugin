import type { Slide } from '../../modules/types';
import { encodeSlideData } from '../../modules/utils';

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
            const src = `${baseUrl}?data=${encodeURIComponent(encodeSlideData(slide))}`;
            const html =
                `<div class="${cls}" data-index="${idx}">` +
                `<iframe src="${src}" sandbox="allow-scripts allow-same-origin" tabindex="-1"></iframe>` +
                `<span class="teksttv-preview-thumb-number">${idx + 1}</span>` +
                '</div>';
            thumbs.insertAdjacentHTML('beforeend', html);
        });
    } else {
        Array.from(thumbs.children).forEach((child, idx) => {
            const el = child instanceof HTMLElement ? child : null;
            if (!el || !slides[idx]) return;
            const newSrc = `${baseUrl}?data=${encodeURIComponent(encodeSlideData(slides[idx]))}`;
            const iframeEl = el.querySelector<HTMLIFrameElement>('iframe');
            el.classList.toggle('is-active', idx === activeIndex);
            if (iframeEl && iframeEl.getAttribute('src') !== newSrc) {
                iframeEl.setAttribute('src', newSrc);
            }
        });
    }
}
