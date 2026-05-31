import type { ImageData, TeksttvPostConfig, WPMediaAttachment, WPMediaFrame } from '../../modules/types';
import { wpMedia } from '../../modules/wpMedia';
import { applySidebarCardState } from './sidebarCard';

/** Custom sidebar-afbeelding via media library + optionele REST-metadata. */
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
        sidebarFrame = wpMedia({ multiple: false, library: { type: 'image' } });
        sidebarFrame.on('select', () => {
            if (!sidebarFrame) return;
            const att: WPMediaAttachment = sidebarFrame.state().get('selection').first().toJSON();
            const url = att.sizes?.medium?.url ?? att.url;
            const idField = document.querySelector<HTMLInputElement>('#teksttv-sidebar-image-id');
            const img = document.querySelector<HTMLImageElement>('#teksttv-sidebar-image-img');
            const placeholder = document.querySelector('#teksttv-sidebar-image-placeholder');
            if (idField) idField.value = String(att.id);
            if (img) {
                img.src = url;
                img.classList.remove('is-hidden');
            }
            placeholder?.classList.add('is-hidden');

            if (config?.imageDataUrl) {
                void fetch(`${config.imageDataUrl}?${new URLSearchParams({ id: String(att.id) })}`, {
                    headers: { 'X-WP-Nonce': config.restNonce },
                    credentials: 'same-origin',
                })
                    .then((res) => res.json())
                    .then((data: ImageData) => {
                        setCustomImageData(data);
                        applySidebarCardState('custom', refreshPreview);
                    })
                    .catch(() => {
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
        sidebarFrame.open();
    };
}
