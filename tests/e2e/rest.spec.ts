import { reseedFixtures } from './reseed-fixtures';
import { expect, test } from './test';

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

    test.describe('iframe contract', () => {
        test.afterEach(reseedFixtures);

        test('serves a representative HTTPS iframe accepted by the frontend schema', async ({
            request,
            runWordPressPHP,
        }) => {
            await runWordPressPHP(
                "update_option('teksttv_loop_tv1', [['type' => 'iframe', 'url' => 'https://example.test/dashboard', 'duration' => 31]]);",
            );

            const response = await request.get('/wp-json/teksttv/v1/slides?channel=tv1');
            expect(response.ok()).toBe(true);
            const data = await response.json();

            expect(data.slides).toContainEqual({
                type: 'iframe',
                url: 'https://example.test/dashboard',
                duration: 31_000,
            });
        });
    });

    test.describe('post metadata persistence', () => {
        test.afterEach(reseedFixtures);

        test('saves through WordPress and changes the packaged REST payload', async ({
            request,
            runWordPressPHPFile,
        }) => {
            const output = await runWordPressPHPFile('save-post-meta.php');
            expect(output).toContain('post-meta-save-ok ');

            const response = await request.get('/wp-json/teksttv/v1/slides?channel=tv1');
            expect(response.ok()).toBe(true);
            const data = await response.json();
            const slide = data.slides.find(
                (candidate: { title?: string }) => candidate.title === 'Opgeslagen via WordPress',
            );

            expect(slide).toMatchObject({
                type: 'text',
                title: 'Opgeslagen via WordPress',
            });
            expect(slide.body.trim()).toBe('<p>Opgeslagen contractinhoud.</p>');
        });
    });
});
