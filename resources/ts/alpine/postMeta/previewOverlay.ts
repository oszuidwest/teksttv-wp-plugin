import type { Slide } from '../../modules/types';
import { previewSlideUrl } from '../../modules/utils';

/** Volledige scherm-overlay voor preview navigeren met pijlen/Escape. */
export function mountTeksttvPreviewOverlay(slides: Slide[], previewUrl: string, initialIndex: number): void {
    if (!slides.length) return;
    let overlayIndex = initialIndex;
    const returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;

    const getOverlaySrc = (idx: number) => previewSlideUrl(previewUrl, slides[idx]);

    const overlay = document.createElement('div');
    overlay.className = 'teksttv-preview-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Preview');
    overlay.innerHTML =
        '<div class="teksttv-overlay-header">' +
        '<button type="button" class="teksttv-overlay-nav-btn teksttv-overlay-prev" aria-label="Vorige previewslide" title="Vorige"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button>' +
        '<span class="teksttv-overlay-counter" aria-live="polite"></span>' +
        '<button type="button" class="teksttv-overlay-nav-btn teksttv-overlay-next" aria-label="Volgende previewslide" title="Volgende"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>' +
        '<button type="button" class="teksttv-preview-overlay-close" aria-label="Preview sluiten" title="Sluiten">&times;</button>' +
        '</div>' +
        '<iframe title="Tekst TV-preview" sandbox="allow-scripts allow-same-origin"></iframe>';
    overlay.querySelector('iframe')?.setAttribute('src', getOverlaySrc(overlayIndex));

    function updateOverlayNav(): void {
        const ctr = overlay.querySelector('.teksttv-overlay-counter');
        if (ctr) ctr.textContent = `${overlayIndex + 1} / ${slides.length}`;
        const prevO = overlay.querySelector<HTMLButtonElement>('.teksttv-overlay-prev');
        const nextO = overlay.querySelector<HTMLButtonElement>('.teksttv-overlay-next');
        if (prevO) prevO.disabled = overlayIndex <= 0;
        if (nextO) nextO.disabled = overlayIndex >= slides.length - 1;
    }

    updateOverlayNav();
    document.body.appendChild(overlay);
    overlay.querySelector<HTMLButtonElement>('.teksttv-preview-overlay-close')?.focus();

    overlay.querySelector('.teksttv-overlay-prev')?.addEventListener('click', () => {
        if (overlayIndex > 0) {
            overlayIndex--;
            overlay.querySelector('iframe')?.setAttribute('src', getOverlaySrc(overlayIndex));
            updateOverlayNav();
        }
    });

    overlay.querySelector('.teksttv-overlay-next')?.addEventListener('click', () => {
        if (overlayIndex < slides.length - 1) {
            overlayIndex++;
            overlay.querySelector('iframe')?.setAttribute('src', getOverlaySrc(overlayIndex));
            updateOverlayNav();
        }
    });

    const keyCtl = new AbortController();
    const teardownKeyNav = (): void => {
        keyCtl.abort();
        overlay.remove();
        returnFocus?.focus();
    };

    overlay.addEventListener('click', (ev) => {
        const tgt = ev.target as Element | null;
        if (
            tgt instanceof Element &&
            (tgt.matches('.teksttv-preview-overlay') || tgt.closest('.teksttv-preview-overlay-close'))
        ) {
            teardownKeyNav();
        }
    });
    document.addEventListener(
        'keydown',
        (ev) => {
            if (ev.key === 'Escape') {
                teardownKeyNav();
            } else if (ev.key === 'ArrowLeft') {
                overlay.querySelector<HTMLButtonElement>('.teksttv-overlay-prev')?.click();
            } else if (ev.key === 'ArrowRight') {
                overlay.querySelector<HTMLButtonElement>('.teksttv-overlay-next')?.click();
            }
        },
        { signal: keyCtl.signal },
    );
}
