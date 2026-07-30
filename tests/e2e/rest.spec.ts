import { expect, test } from '@playwright/test';

test.describe('slides REST endpoint', () => {
    test('backfills the eligible slide past a full batch of ineligible newer posts', async ({ request }) => {
        const res = await request.get('/wp-json/teksttv/v1/slides?channel=tv1');
        expect(res.ok()).toBeTruthy();

        const data = await res.json();
        expect(Array.isArray(data.slides)).toBe(true);
        expect(Array.isArray(data.ticker)).toBe(true);

        // The fixtures seed ten newer runtime-ineligible posts (filling the
        // first query batch) plus a scheduled-out post; the smoke post is only
        // reachable when the articles block backfills into a second batch.
        const textSlide = data.slides.find((s: { type?: string }) => s.type === 'text');
        expect(textSlide, 'a text slide is present').toBeTruthy();
        expect(textSlide.title).toBe('TekstTV Smoke Post');
        expect(typeof textSlide.duration).toBe('number');

        const titles = data.slides.map((s: { title?: string }) => s.title);
        expect(titles).not.toContain('TekstTV Toekomstig Bericht');
        expect(titles.filter((t?: string) => t?.startsWith('TekstTV Backfill Vulling'))).toEqual([]);

        const hasTicker = data.ticker.some(
            (t: { message?: string }) => typeof t.message === 'string' && t.message.length > 0,
        );
        expect(hasTicker, 'a ticker message is present').toBe(true);
    });

    test('rejects an unknown channel', async ({ request }) => {
        const res = await request.get('/wp-json/teksttv/v1/slides?channel=does-not-exist');
        expect(res.ok()).toBeFalsy();
    });
});
