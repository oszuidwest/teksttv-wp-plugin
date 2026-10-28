import { addLoopBlock, getBrowserErrors, login, submitAndReload } from './helpers';
import { reseedFixtures } from './reseed-fixtures';
import { expect, type Page, type RunWordPressPHP, test } from './test';

interface Slide {
    type?: string;
    title?: string;
    body?: string;
}

// Plain-text editor keeps the form fillable without TinyMCE.
const ENABLE_MESSAGES = `
update_option('teksttv_features', ['scheduling', 'page_separator', 'messages']);
update_option('teksttv_channels', [['slug' => 'tv1', 'label' => 'TV 1'], ['slug' => 'tv2', 'label' => 'TV 2']]);
update_option('teksttv_loop_tv1', [['type' => 'messages', 'count' => 10]]);
update_option('teksttv_loop_tv2', [['type' => 'messages', 'count' => 10]]);
`;

async function slideTitles(page: Page, channel: string): Promise<string[]> {
    const response = await page.request.get(`/wp-json/teksttv/v1/slides?channel=${channel}`);
    expect(response.ok()).toBe(true);
    const data = (await response.json()) as { slides: Slide[] };
    return data.slides.filter((slide) => slide.type === 'text').map((slide) => slide.title ?? '');
}

async function createMessage(
    runWordPressPHP: RunWordPressPHP,
    title: string,
    { channels = [] as string[], status = 'publish', date = '', start = '', end = '' } = {},
): Promise<void> {
    const output = await runWordPressPHP(`
$id = wp_insert_post([
    'post_type' => 'teksttv_message',
    'post_status' => ${JSON.stringify(status)},
    'post_title' => ${JSON.stringify(title)},
    ${date ? `'post_date' => ${JSON.stringify(date)},` : ''}
], true);
if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
update_post_meta($id, '_teksttv_active', '1');
update_post_meta($id, '_teksttv_content', 'Tekst van ' . ${JSON.stringify(title)});
update_post_meta($id, '_teksttv_channels', ${JSON.stringify(channels)});
update_post_meta($id, '_teksttv_date_start', ${JSON.stringify(start)});
update_post_meta($id, '_teksttv_date_end', ${JSON.stringify(end)});
echo 'message-ok';
`);
    expect(output).toContain('message-ok');
}

test.describe('standalone messages', () => {
    test.afterEach(async ({ page, runWordPressPHPFile }) => {
        try {
            expect(await getBrowserErrors(page)).toEqual([]);
        } finally {
            await reseedFixtures({ runWordPressPHPFile });
        }
    });

    test('air per channel, ignore the article age limit and skip drafts', async ({ page, runWordPressPHP }) => {
        await runWordPressPHP(ENABLE_MESSAGES);
        await createMessage(runWordPressPHP, 'Overal');
        await createMessage(runWordPressPHP, 'Alleen TV 2', { channels: ['tv2'] });
        await createMessage(runWordPressPHP, 'Oud bericht', { date: '2020-01-01 12:00:00' });
        await createMessage(runWordPressPHP, 'Concept', { status: 'draft' });

        expect((await slideTitles(page, 'tv1')).sort()).toEqual(['Oud bericht', 'Overal']);
        expect((await slideTitles(page, 'tv2')).sort()).toEqual(['Alleen TV 2', 'Oud bericht', 'Overal']);
    });

    test('can be placed in a loop from the admin screen', async ({ page, runWordPressPHP }) => {
        await runWordPressPHP(`${ENABLE_MESSAGES}update_option('teksttv_loop_tv1', []);`);
        await createMessage(runWordPressPHP, 'Via de loop');

        await page.goto('/wp-admin/admin.php?page=teksttv-loop-tv1');
        const block = await addLoopBlock(page, 'messages');
        await block.locator('.teksttv-block-toggle-control').click();
        await block.locator('input[name$="[count]"]').fill('2');
        await submitAndReload(page);

        const saved = page.locator('#teksttv-blocks > .teksttv-block[data-type="messages"]');
        await expect(saved).toHaveCount(1);
        await expect(saved.locator('input[name$="[count]"]')).toHaveValue('2');
        expect(await slideTitles(page, 'tv1')).toEqual(['Via de loop']);
    });

    test('overview shows the air status and filters by it', async ({ page, runWordPressPHP }) => {
        await runWordPressPHP(ENABLE_MESSAGES);
        const day = (offset: number): string => {
            const date = new Date();
            date.setUTCDate(date.getUTCDate() + offset);
            return date.toISOString().slice(0, 10);
        };
        // A day of margin keeps the test independent of the site timezone.
        await createMessage(runWordPressPHP, 'Loopt nu', { start: day(-3), end: day(3) });
        await createMessage(runWordPressPHP, 'Begint later', { start: day(5), end: day(9) });
        await createMessage(runWordPressPHP, 'Al voorbij', { start: day(-9), end: day(-5) });
        await createMessage(runWordPressPHP, 'Nog een concept', { status: 'draft' });

        await page.goto('/wp-admin/edit.php?post_type=teksttv_message');
        const rowState = (title: string) =>
            page.locator('#the-list tr', { hasText: title }).locator('.teksttv-message-state');
        await expect(rowState('Loopt nu')).toHaveText('Op Tekst TV');
        await expect(rowState('Begint later')).toHaveText('Komt nog');
        await expect(rowState('Al voorbij')).toHaveText('Verlopen');
        // Core's own status label, translated per site locale.
        await expect(rowState('Nog een concept')).toHaveText(/^(Draft|Concept)$/);

        const views = page.locator('.subsubsub');
        await expect(views.getByRole('link', { name: 'Actief (1)' })).toBeVisible();
        await expect(views.getByRole('link', { name: 'Komt nog (1)' })).toBeVisible();
        await views.getByRole('link', { name: 'Verlopen (1)' }).click();

        await expect(page.locator('#the-list tr')).toHaveCount(1);
        await expect(page.locator('#the-list')).toContainText('Al voorbij');
        await expect(views.getByRole('link', { name: 'Verlopen (1)' })).toHaveAttribute('aria-current', 'page');
    });

    test('stay off air while the feature is disabled', async ({ page, runWordPressPHP }) => {
        await runWordPressPHP(ENABLE_MESSAGES);
        await createMessage(runWordPressPHP, 'Overal');
        await runWordPressPHP("update_option('teksttv_features', ['scheduling']);");

        expect(await slideTitles(page, 'tv1')).toEqual([]);
        expect((await page.request.get('/wp-admin/edit.php?post_type=teksttv_message')).ok()).toBe(false);
    });

    test('are not exposed through the public site or core REST API', async ({ page, runWordPressPHP }) => {
        await runWordPressPHP(ENABLE_MESSAGES);
        await createMessage(runWordPressPHP, 'Geheim bericht');

        const rest = await page.request.get('/wp-json/wp/v2/types');
        expect(Object.keys(await rest.json())).not.toContain('teksttv_message');
        const search = await page.request.get('/?s=Geheim');
        expect(await search.text()).not.toContain('Geheim bericht');
    });

    test('a role with only message capabilities can publish a message for one channel', async ({
        browser,
        baseURL,
        page,
        runWordPressPHP,
    }) => {
        await runWordPressPHP(`${ENABLE_MESSAGES}
$caps = array_fill_keys(\\TekstTV\\Messages::CAPABILITIES, true);
add_role('teksttv_message_role', 'Tekst TV-berichten', ['read' => true] + $caps);
$uid = wp_create_user('teksttv_message_editor', 'password', 'teksttv_message_editor@example.test');
if (is_wp_error($uid)) { throw new RuntimeException($uid->get_error_message()); }
(new WP_User($uid))->set_role('teksttv_message_role');
`);

        const context = await browser.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
        const editor = await context.newPage();
        try {
            await login(editor, 'teksttv_message_editor', 'password');

            // Without edit_teksttv or edit_posts the user only reaches messages.
            await expect(
                editor.locator('#adminmenu a[href="edit.php?post_type=teksttv_message"]').first(),
            ).toBeVisible();
            await expect(editor.locator('#adminmenu a[href="edit.php"]')).toHaveCount(0);

            await editor.goto('/wp-admin/post-new.php?post_type=teksttv_message');
            await expect(editor.locator('#teksttv_meta')).toBeVisible();
            await expect(editor.locator('.teksttv-toggle-bar')).toBeHidden();
            await expect(editor.locator('#teksttv-title')).toHaveCount(0);

            await editor.fill('#title', 'Wegafsluiting Haagweg');
            await editor.fill('#teksttv_content', 'De Haagweg is dit weekend afgesloten.');
            await editor.check('input[name="teksttv_channels[]"][value="tv2"]');
            await editor.click('#publish');
            await expect(editor.locator('#message')).toBeVisible();
            await expect(editor.locator('input[name="teksttv_channels[]"][value="tv2"]')).toBeChecked();

            expect(await getBrowserErrors(editor)).toEqual([]);
        } finally {
            await context.close();
        }

        expect(await slideTitles(page, 'tv1')).toEqual([]);
        expect(await slideTitles(page, 'tv2')).toEqual(['Wegafsluiting Haagweg']);
    });
});
