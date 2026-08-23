import Alpine from 'alpinejs';
import { initTomSelectIn } from '../modules/tomSelect';
import { createBlocksWorkbench } from './blocks/workbench';
import { createCategoryMediaPage } from './categoryMedia';
import { createChannelsSettingsPage } from './channelsSettings';
import { createPostMetaPage } from './postMetaPage';

/** Run component initialization before TomSelect. */
function withTomSelect<T extends { init(this: unknown): void }>(component: T): T {
    const originalInit = component.init;
    return {
        ...component,
        init(this: unknown): void {
            originalInit.call(this);
            initTomSelectIn(document);
        },
    };
}

/**
 * Keep one Alpine scope per admin screen; behavior lives in focused modules.
 */
export function registerTeksttvAlpine(): void {
    Alpine.data('teksttvLoopPage', () =>
        withTomSelect(createBlocksWorkbench({ ticker: true, commercialBlocks: false, campaignAdd: false })),
    );

    Alpine.data('teksttvCommercialsPage', () =>
        withTomSelect(createBlocksWorkbench({ ticker: false, commercialBlocks: true, campaignAdd: true })),
    );

    Alpine.data('teksttvSettingsPage', () => withTomSelect(createChannelsSettingsPage()));

    Alpine.data('teksttvPostMetaPage', () => withTomSelect(createPostMetaPage()));

    Alpine.data('teksttvCategoryMedia', () => createCategoryMediaPage());
}
