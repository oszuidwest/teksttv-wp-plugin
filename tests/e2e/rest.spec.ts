import { expect, test } from '@playwright/test';

test.describe('slides REST endpoint', () => {
    test('returns the expected slide and ticker shape', async ({ request }) => {
        const res = await request.get('/wp-json/teksttv/v1/slides?channel=tv1');
        expect(res.ok()).toBeTruthy();

        const data = await res.json();
        expect(Array.isArray(data.slides)).toBe(true);
        expect(Array.isArray(data.ticker)).toBe(true);

        const textSlide = data.slides.find((s: { type?: string }) => s.type === 'text');
        expect(textSlide, 'a text slide is present').toBeTruthy();
        expect(textSlide.title).toBe('TekstTV Smoke Post');
        expect(typeof textSlide.duration).toBe('number');

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
