import { type Page, expect, test } from '@playwright/test';
import { addLoopBlock } from './helpers';

type EditorWindow = Window & {
    wp?: {
        data?: {
            dispatch?: (store: unknown) => { set: (scope: string, name: string, value: boolean) => void };
        };
        preferences?: { store?: unknown };
    };
};

async function selectFixtureImage(page: Page): Promise<string> {
    const modal = page.locator('.media-modal:visible');
    await expect(modal).toBeVisible();
    const libraryTab = modal.getByRole('tab', { name: 'Media Library' });
    await expect(libraryTab).toBeVisible();
    if ((await libraryTab.getAttribute('aria-selected')) !== 'true') {
        await libraryTab.click();
    }
    await expect(libraryTab).toHaveAttribute('aria-selected', 'true');

    const attachment = modal.getByRole('checkbox', { name: 'TekstTV E2E Image' });
    await expect(attachment).toBeVisible();
    const attachmentId = await attachment.getAttribute('data-id');
    if (!attachmentId) throw new Error('The E2E media fixture must expose its attachment ID.');

    await attachment.click();
    await expect(attachment).toHaveAttribute('aria-checked', 'true');
    const selectButton = modal.locator('.media-button-select');
    await expect(selectButton).toBeEnabled();
    await selectButton.click();
    await expect(modal).toBeHidden();
    return attachmentId;
}

async function openFixturePostEditor(page: Page): Promise<void> {
    await page.goto('/wp-admin/edit.php');
    await page.getByRole('link', { name: 'TekstTV Smoke Post' }).first().click();

    await expect(page.locator('#teksttv_meta')).toBeAttached();
    await page.waitForFunction(() => {
        const { wp } = window as EditorWindow;
        return typeof wp?.data?.dispatch === 'function' && wp?.preferences?.store !== undefined;
    });
    await page.evaluate(() => {
        const { wp } = window as EditorWindow;
        if (!wp?.data?.dispatch || !wp.preferences) return;
        wp.data.dispatch(wp.preferences.store).set('core/edit-post', 'welcomeGuide', false);
    });

    const metaBoxesButton = page.getByText('Meta Boxes', { exact: true });
    await expect(page.locator('.edit-post-welcome-guide')).toBeHidden();
    await expect(metaBoxesButton).toBeVisible();
    if ((await metaBoxesButton.getAttribute('aria-expanded')) !== 'true') {
        await metaBoxesButton.click();
    }
    await expect(metaBoxesButton).toHaveAttribute('aria-expanded', 'true');
}

// No reseed hooks here: none of these tests submit a form or save the post,
// and the fixture attachment is created idempotently, so nothing persists.
test.describe('media picker interactions', () => {
    test('ignores stale sidebar metadata after a newer card selection', async ({ page }) => {
        test.setTimeout(45_000);
        await openFixturePostEditor(page);

        let markRequestStarted: () => void = () => {};
        const requestStarted = new Promise<void>((resolve) => {
            markRequestStarted = resolve;
        });
        let releaseResponse: () => void = () => {};
        const responseReleased = new Promise<void>((resolve) => {
            releaseResponse = resolve;
        });

        await page.route('**/wp-json/teksttv/v1/image-data?**', async (route) => {
            markRequestStarted();
            await responseReleased;
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ url: 'https://example.test/stale-sidebar.jpg' }),
            });
        });

        const customCard = page.locator('#teksttv-sidebar-card-custom');
        await customCard.click();
        await selectFixtureImage(page);
        await requestStarted;

        const idField = page.locator('#teksttv-sidebar-image-id');
        const noneCard = page.locator('#teksttv-sidebar-card-none');
        await noneCard.click();
        await expect(idField).toHaveValue('0');
        await expect(noneCard).toHaveClass(/is-active/);

        const response = page.waitForResponse('**/wp-json/teksttv/v1/image-data?**');
        releaseResponse();
        await response;
        await page.waitForTimeout(100);

        await expect(idField).toHaveValue('0');
        await expect(noneCard).toHaveClass(/is-active/);
        await expect(customCard).not.toHaveClass(/is-active/);
    });

    test('sets and clears an image block attachment and preview', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-loop-tv1');
        const imageBlock = await addLoopBlock(page, 'image');

        const idInput = imageBlock.locator('.teksttv-block-image-id');
        const preview = imageBlock.locator('.teksttv-block-image-preview');
        const thumbnail = imageBlock.locator('.teksttv-block-image-thumb');
        const removeButton = imageBlock.locator('.teksttv-block-image-remove');

        await imageBlock.locator('.teksttv-block-image-select').click();
        const attachmentId = await selectFixtureImage(page);

        await expect(idInput).toHaveValue(attachmentId);
        await expect(preview).toBeVisible();
        await expect(thumbnail).toHaveAttribute('src', /.+/);
        await expect(removeButton).toBeVisible();

        await removeButton.click();
        await expect(idInput).toHaveValue('');
        await expect(preview).toBeHidden();
        await expect(thumbnail).not.toHaveAttribute('src', /.+/);
        await expect(removeButton).toBeHidden();
    });

    test('sets and clears a campaign intro image through the shared picker contract', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-loop-tv1');
        const campaignBlock = await addLoopBlock(page, 'campaign');

        // First picker in the block is the intro transition image.
        const introPicker = campaignBlock.locator('.teksttv-image-picker').first();
        const idInput = introPicker.locator('.teksttv-block-image-id');
        const preview = introPicker.locator('.teksttv-block-image-preview');
        const removeButton = introPicker.locator('.teksttv-block-image-remove');

        await introPicker.locator('.teksttv-block-image-select').click();
        const attachmentId = await selectFixtureImage(page);

        await expect(idInput).toHaveValue(attachmentId);
        await expect(preview).toBeVisible();
        await expect(introPicker.locator('.teksttv-block-image-thumb')).toHaveAttribute('src', /.+/);
        await expect(removeButton).toBeVisible();
        await expect(campaignBlock.locator('.teksttv-block-summary')).toContainText('Intro afbeelding');

        await removeButton.click();
        await expect(idInput).toHaveValue('');
        await expect(preview).toBeHidden();
        await expect(removeButton).toBeHidden();
        await expect(campaignBlock.locator('.teksttv-block-summary')).not.toContainText('Intro afbeelding');
    });

    test('keeps extra-image removal in sync with the form and preview', async ({ page }) => {
        test.setTimeout(45_000);
        await openFixturePostEditor(page);

        const list = page.locator('#teksttv-images-list');
        const items = list.locator(':scope > .teksttv-image-item');
        const previewCounter = page.locator('#teksttv-preview-counter');
        const existingItem = items.first();

        await expect(items).toHaveCount(1);
        await expect(previewCounter).toHaveText('1 / 2');

        const inputDisabledImmediately = await existingItem.locator('.teksttv-remove-image').evaluate((button) => {
            button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
            const input = button.closest('.teksttv-image-item')?.querySelector('input[name="teksttv_images[]"]');
            return input instanceof HTMLInputElement && input.disabled;
        });
        expect(inputDisabledImmediately).toBe(true);
        await expect(existingItem).toHaveCount(0);
        await expect(previewCounter).toHaveText('1 / 1');

        const addImagesButton = page.locator('#teksttv-add-images');
        await addImagesButton.click();
        const attachmentId = await selectFixtureImage(page);

        const addedItem = items.filter({ has: page.locator(`input[value="${attachmentId}"]`) });
        await expect(addedItem).toHaveCount(1);
        await expect(addedItem.locator('input[name="teksttv_images[]"]')).toHaveValue(attachmentId);
        await expect(addedItem.locator('img')).toHaveAttribute('src', /.+/);

        await addedItem.locator('.teksttv-remove-image').click();
        await expect(addedItem).toHaveCount(0);
        await expect(previewCounter).toHaveText('1 / 1');
    });
});
