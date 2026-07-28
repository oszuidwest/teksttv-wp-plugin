import type { ImageData, Slide, TeksttvPostConfig, TextSlide } from '../../modules/types';
import { splitPages } from '../../modules/utils';
import { getTeksttvEditorHtml } from './editorContent';

export function resolveSidebarImage(
    config: TeksttvPostConfig | undefined,
    customImageData: ImageData | null,
): ImageData | null {
    const cards = document.querySelector('.teksttv-image-cards');
    const activeState = (cards instanceof HTMLElement && cards.dataset.active) || 'default';
    if (activeState === 'custom') {
        return customImageData;
    }
    if (activeState === 'default') {
        const fallback = config?.fallbackImage;
        return fallback && typeof fallback === 'object' ? fallback : null;
    }
    return null;
}

export function hasSidebarPhoto(config: TeksttvPostConfig | undefined, customImageData: ImageData | null): boolean {
    return resolveSidebarImage(config, customImageData) !== null;
}

/** Leest kop, body en afbeeldingslijst uit de DOM naar preview-slides. */
export function buildSlidesFromDom(config: TeksttvPostConfig | undefined, customImageData: ImageData | null): Slide[] {
    const customTitle = (document.querySelector<HTMLInputElement>('#teksttv-title')?.value ?? '').trim();
    const postTitle = (
        (document.querySelector<HTMLInputElement>('#title')?.value ?? '') ||
        (document.querySelector<HTMLInputElement>('input[name="post_title"]')?.value ?? '') ||
        ''
    ).trim();
    const placeholderTitle =
        document.querySelector<HTMLInputElement>('#teksttv-title')?.getAttribute('placeholder') ?? '';
    const title = customTitle || postTitle || placeholderTitle;
    const content = getTeksttvEditorHtml();
    const result: Slide[] = [];

    const expandedPages = splitPages(content);

    const sidebarImg = resolveSidebarImage(config, customImageData);

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

    const imageItemsRoot = document.querySelector('#teksttv-images-list');
    (imageItemsRoot ? imageItemsRoot.querySelectorAll('.teksttv-image-item') : []).forEach((item) => {
        const img = item.querySelector<HTMLImageElement>('img');
        if (img) {
            result.push({
                type: 'image',
                duration: 7000,
                url: (img.getAttribute('src') ?? '').replace(/-\d+x\d+\./, '.'),
            });
        }
    });

    return result;
}
