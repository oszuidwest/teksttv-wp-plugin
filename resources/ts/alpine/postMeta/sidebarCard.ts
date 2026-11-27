import type { ImageData, TeksttvPostConfig } from '../../modules/types';
import { watchFeaturedImage } from './editorContent';

export function applySidebarCardState(state: string, refreshPreview: () => void): void {
    for (const c of document.querySelectorAll('.teksttv-image-card')) {
        const selected = c instanceof HTMLElement && c.dataset.state === state;
        c.classList.toggle('is-active', selected);
        c.setAttribute('aria-pressed', String(selected));
    }
    const cards = document.querySelector('.teksttv-image-cards');
    if (cards instanceof HTMLElement) cards.dataset.active = state;

    if (state === 'none') {
        const sidNone = document.querySelector<HTMLInputElement>('#teksttv-sidebar-image-id');
        if (sidNone) sidNone.value = '0';
    } else if (state === 'default') {
        const sid = document.querySelector<HTMLInputElement>('#teksttv-sidebar-image-id');
        if (sid) sid.value = '';
    }
    refreshPreview();
}

/** Resolve an attachment to TV sidebar image data; rejects when it has no usable URL. */
export async function fetchSidebarImageData(config: TeksttvPostConfig, id: string): Promise<ImageData> {
    const data = await wp.apiFetch<ImageData>({
        url: `${config.imageDataUrl}?${new URLSearchParams({ id, slot: 'text_sidebar' })}`,
    });
    if (!data.url) throw new Error('image-data request failed');
    return data;
}

/** Keep the default sidebar image in step with the featured image set in the block editor. */
export function followFeaturedImage(config: TeksttvPostConfig, refreshPreview: () => void): void {
    let latestRequest = 0;
    watchFeaturedImage(async (id) => {
        const request = ++latestRequest;
        let data: ImageData | null = null;
        if (id) {
            try {
                data = await fetchSidebarImageData(config, String(id));
            } catch (error) {
                // A replaced lookup changes nothing, so it does not warn.
                if (request === latestRequest) {
                    console.warn(
                        `TekstTV: uitgelichte afbeelding ${id} kon niet worden opgehaald; de standaardafbeelding wordt leeggemaakt.`,
                        error,
                    );
                }
            }
        }
        // Drop this result if a newer change came in, even one back to the same image (A, B, A).
        if (request !== latestRequest) return;
        config.fallbackImage = data ?? '';
        const img = document.querySelector<HTMLImageElement>('#teksttv-sidebar-default-img');
        if (img) {
            img.src = data?.url ?? '';
            img.classList.toggle('is-hidden', !data);
        }
        document.querySelector('#teksttv-sidebar-default-placeholder')?.classList.toggle('is-hidden', !!data);
        refreshPreview();
    });
}
