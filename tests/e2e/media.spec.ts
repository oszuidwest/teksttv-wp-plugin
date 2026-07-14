import { type Locator, type Page, expect, test } from '@playwright/test';
import { login } from './helpers';

async function selectFixtureImage(page: Page): Promise<string> {
    const modal = page.locator('.media-modal:visible');
    await expect(modal).toBeVisible();
    const libraryTab = modal.getByRole('tab', { name: 'Media Library' });
    await expect(libraryTab).toBeVisible();
    await expect
        .poll(async () => {
            if ((await libraryTab.getAttribute('aria-selected')) !== 'true') {
                await libraryTab.focus();
                await page.keyboard.press('Enter');
            }
            return libraryTab.getAttribute('aria-selected');
        })
        .toBe('true');

    const attachment = modal.getByRole('checkbox', { name: 'TekstTV E2E Image' });
    await expect(attachment).toBeVisible();
    const attachmentId = await attachment.getAttribute('data-id');
    if (!attachmentId) throw new Error('The E2E media fixture must expose its attachment ID.');

    await attachment.focus();
    await page.keyboard.press('Space');
    await expect(attachment).toHaveAttribute('aria-checked', 'true');
    const selectButton = modal.locator('.media-button-select');
    await expect(selectButton).toBeEnabled();
    await selectButton.focus();
    await page.keyboard.press('Enter');
    await expect(modal).toBeHidden();
    return attachmentId;
}

async function addImageBlock(page: Page): Promise<Locator> {
    const blocks = page.locator('#teksttv-blocks > .teksttv-block');
    const previousCount = await blocks.count();
    await page.locator('#teksttv-add-block-toggle').click();
    await page.locator('#teksttv-add-block-menu button[data-type="image"]').click();
    await expect(blocks).toHaveCount(previousCount + 1);
    return blocks.last();
}

test.describe('media picker interactions', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, 'admin', 'password');
    });

    test('sets and clears an image block attachment and preview', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-loop-tv1');
        const imageBlock = await addImageBlock(page);

        const idInput = imageBlock.locator('.teksttv-block-image-id');
        const preview = imageBlock.locator('.teksttv-block-image-preview');
        const thumbnail = imageBlock.locator('.teksttv-block-image-thumb');
        const removeButton = imageBlock.locator('.teksttv-block-image-remove');

        await imageBlock.locator('.teksttv-block-image-select').click();
        const attachmentId = await selectFixtureImage(page);

        await expect(idInput).toHaveValue(attachmentId);
        await expect(preview).toBeVisible();
        await expect(thumbnail).not.toHaveAttribute('src', '');
        await expect(removeButton).toBeVisible();

        await removeButton.click();
        await expect(idInput).toHaveValue('');
        await expect(preview).toBeHidden();
        await expect(thumbnail).not.toHaveAttribute('src', /.+/);
        await expect(removeButton).toBeHidden();
    });

    test('adds and removes an extra image in the post meta box', async ({ page }) => {
        test.setTimeout(45_000);
        await page.goto('/wp-admin/edit.php');
        await page.getByRole('link', { name: 'TekstTV Smoke Post' }).first().click();

        await expect(page.locator('#teksttv_meta')).toBeAttached();
        await page.waitForFunction(
            () =>
                typeof (
                    window as unknown as {
                        wp?: { data?: { dispatch?: unknown }; preferences?: { store?: unknown } };
                    }
                ).wp?.data?.dispatch === 'function',
        );
        await page.evaluate(() => {
            const editorWp = (
                window as unknown as {
                    wp: {
                        data: {
                            dispatch: (store: unknown) => {
                                set: (scope: string, name: string, value: boolean) => void;
                            };
                        };
                        preferences: { store: unknown };
                    };
                }
            ).wp;
            editorWp.data.dispatch(editorWp.preferences.store).set('core/edit-post', 'welcomeGuide', false);
        });

        const metaBoxesButton = page.getByText('Meta Boxes', { exact: true });
        await expect(page.locator('.edit-post-welcome-guide')).toBeHidden();
        await expect(metaBoxesButton).toBeVisible();
        await page.waitForTimeout(300);
        await expect
            .poll(async () => {
                if ((await metaBoxesButton.getAttribute('aria-expanded')) !== 'true') {
                    await metaBoxesButton.focus();
                    await page.keyboard.press('Enter');
                }
                return metaBoxesButton.getAttribute('aria-expanded');
            })
            .toBe('true');

        const list = page.locator('#teksttv-images-list');
        const items = list.locator(':scope > .teksttv-image-item');
        const addImagesButton = page.locator('#teksttv-add-images');
        await expect(addImagesButton).toBeVisible();
        await addImagesButton.focus();
        await expect(addImagesButton).toBeFocused();
        await page.keyboard.press('Enter');
        const attachmentId = await selectFixtureImage(page);

        const addedItem = items.filter({ has: page.locator(`input[value="${attachmentId}"]`) });
        await expect(addedItem).toHaveCount(1);
        await expect(addedItem.locator('input[name="teksttv_images[]"]')).toHaveValue(attachmentId);
        await expect(addedItem.locator('img')).not.toHaveAttribute('src', '');

        await addedItem.locator('.teksttv-remove-image').focus();
        await page.keyboard.press('Enter');
        await expect(addedItem).toHaveCount(0);
    });
});
