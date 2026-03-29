import type { Slide } from './types';

/** Encode a slide object to a base64 string for the preview URL. */
export function encodeSlideData(slide: Slide): string {
    const json = JSON.stringify(slide);
    const bytes = new TextEncoder().encode(json);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
}

/** Initialize TomSelect on elements within a container. */
export function initTomSelectIn(container: Element | Document = document): void {
    if (typeof TomSelect === 'undefined') return;

    container.querySelectorAll<HTMLSelectElement>('.teksttv-tomselect').forEach((el) => {
        if ((el as any).tomselect) return;
        new TomSelect(el, {
            plugins: ['remove_button'],
            placeholder: el.dataset.placeholder || 'Filter...',
            allowEmptyOption: true,
        });
    });
}
