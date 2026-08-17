import type { ImageData, TeksttvPostConfig, WPMediaFrame } from '../../modules/types';
import { pickSingleImage } from '../../modules/wpMedia';
import { applySidebarCardState } from './sidebarCard';

/**
 * Pick a sidebar image, falling back to attachment data if REST fails.
 */
export function createSidebarCustomPicker(
    config: TeksttvPostConfig | undefined,
    setCustomImageData: (data: ImageData | null) => void,
    refreshPreview: () => void,
): () => void {
    let sidebarFrame: WPMediaFrame | null = null;

    return (): void => {
        if (sidebarFrame) {
            sidebarFrame.open();
            return;
        }
        sidebarFrame = pickSingleImage((att) => {
            const selectedId = String(att.id);
            const url = att.sizes?.medium?.url ?? att.url;
            const idField = document.querySelector<HTMLInputElement>('#teksttv-sidebar-image-id');
            const img = document.querySelector<HTMLImageElement>('#teksttv-sidebar-image-img');
            const placeholder = document.querySelector('#teksttv-sidebar-image-placeholder');
            if (idField) idField.value = selectedId;
            if (img) {
                img.src = url;
                img.classList.remove('is-hidden');
            }
            placeholder?.classList.add('is-hidden');

            if (config?.imageDataUrl) {
                // A changed ID marks this response as stale.
                const selectionIsCurrent = (): boolean =>
                    document.querySelector<HTMLInputElement>('#teksttv-sidebar-image-id')?.value === selectedId;

                void wp
                    .apiFetch<ImageData>({
                        url: `${config.imageDataUrl}?${new URLSearchParams({ id: selectedId, slot: 'text_sidebar' })}`,
                    })
                    .then((data) => {
                        if (!selectionIsCurrent()) return;
                        if (!data.url) {
                            throw new Error('image-data request failed');
                        }
                        setCustomImageData(data);
                        applySidebarCardState('custom', refreshPreview);
                    })
                    .catch(() => {
                        if (!selectionIsCurrent()) return;
                        console.warn(
                            'TekstTV: metadata voor de sidebar-afbeelding kon niet worden opgehaald; de onbewerkte bijlage-URL wordt gebruikt.',
                        );
                        const fullUrl = att.sizes?.large?.url ?? att.url;
                        const imgData: ImageData = { url: fullUrl };
                        if (att.caption) imgData.caption = att.caption;
                        setCustomImageData(imgData);
                        applySidebarCardState('custom', refreshPreview);
                    });
            } else {
                const imgData: ImageData = { url: att.sizes?.large?.url ?? att.url };
                if (att.caption) imgData.caption = att.caption;
                setCustomImageData(imgData);
                applySidebarCardState('custom', refreshPreview);
            }
        });
    };
}
