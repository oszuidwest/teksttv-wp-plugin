import { expect, test } from 'bun:test';
import { followFeaturedImage } from '../../resources/ts/alpine/postMeta/sidebarCard';
import type { ImageData, TeksttvPostConfig } from '../../resources/ts/modules/types';

function setGlobal(name: string, value: unknown): void {
    Object.defineProperty(globalThis, name, { configurable: true, value });
}

test('skips the loaded featured image and keeps the latest of A, B, A', async () => {
    let featured: number | undefined;
    let notify = () => {};
    const lookups: Array<(data: ImageData) => void> = [];
    setGlobal('wp', {
        data: {
            select: () => ({ getEditedPostAttribute: () => featured }),
            subscribe: (listener: () => void) => {
                notify = listener;
            },
        },
        apiFetch: () => new Promise<ImageData>((resolve) => lookups.push(resolve)),
    });
    setGlobal('document', { querySelector: () => null });
    const config = { imageDataUrl: '/image-data', fallbackImage: '' } as TeksttvPostConfig;
    let refreshes = 0;
    followFeaturedImage(config, () => refreshes++);

    for (const id of [0, 7, 8, 7]) {
        featured = id; // 0 is the saved value the store loads; then A, B, A.
        notify();
    }
    expect(lookups).toHaveLength(3);
    lookups[2]({ url: 'https://example.test/new.jpg' } as ImageData);
    lookups[0]({ url: 'https://example.test/old.jpg' } as ImageData);
    lookups[1]({ url: 'https://example.test/8.jpg' } as ImageData);
    await Bun.sleep(0);

    expect(config.fallbackImage).toMatchObject({ url: 'https://example.test/new.jpg' });
    expect(refreshes).toBe(1);
});
