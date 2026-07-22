import Alpine from 'alpinejs';
import { initTomSelectIn } from '../modules/utils';
import { createBlocksWorkbench } from './blocks/workbench';
import { createCategoryMediaPage } from './categoryMedia';
import { createChannelsSettingsPage } from './channelsSettings';
import { createPostMetaPage } from './postMetaPage';

/** Wrap a component's `init` so TomSelect is initialized after it runs. */
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
 * One `Alpine.data` per WP adminscherm houdt bootstrap simpel (geen geneste scopes die parent's
 * methods missen). Zware logika zit in losse TS-modules onder `alpine/blocks/` e.d.
 */
export function registerTeksttvAlpine(): void {
    Alpine.data('teksttvLoopPage', () =>
        withTomSelect(createBlocksWorkbench({ ticker: true, groups: false, campaignAdd: false })),
    );

    Alpine.data('teksttvCampaignsPage', () =>
        withTomSelect(createBlocksWorkbench({ ticker: false, groups: true, campaignAdd: true })),
    );

    Alpine.data('teksttvSettingsPage', () => withTomSelect(createChannelsSettingsPage()));

    Alpine.data('teksttvPostMetaPage', () => withTomSelect(createPostMetaPage()));

    Alpine.data('teksttvCategoryMedia', () => createCategoryMediaPage());
}
