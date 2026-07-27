import type { ImageData, TeksttvPostConfig, WPMediaFrame } from '../../modules/types';
import { pickSingleImage } from '../../modules/wpMedia';
import { applySidebarCardState } from './sidebarCard';

/**
 * Pick a custom sidebar image. The REST endpoint supplies the normalized slide metadata;
 * the attachment URL and caption are the fallback when that request fails.
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
                void fetch(
                    `${config.imageDataUrl}?${new URLSearchParams({ id: String(att.id), slot: 'text_sidebar' })}`,
                    {
                        headers: { 'X-WP-Nonce': config.restNonce },
                        credentials: 'same-origin',
                    },
                )
                    .then(async (res) => ({
                        ok: res.ok,
                        data: (await res.json()) as ImageData | { error?: string; message?: string },
                    }))
                    .then(({ ok, data }) => {
                        // {error} is the plugin's 404 shape; {code, message}
                        // is a WordPress core rejection (e.g. expired nonce).
                        if (!ok || 'error' in data || !('url' in data) || !data.url) {
                            throw new Error('image-data request failed');
                        }
                        setCustomImageData(data);
                        applySidebarCardState('custom', refreshPreview);
                    })
                    .catch(() => {
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
