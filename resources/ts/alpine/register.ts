import Alpine from 'alpinejs';
import { initTomSelectIn } from '../modules/utils';
import { createBlocksWorkbench } from './blocks/workbench';
import { createCategoryMediaPage } from './categoryMedia';
import { createChannelsSettingsPage } from './channelsSettings';
import { createPostMetaPage } from './postMetaPage';

function wrapWorkbenchInit(opts: Parameters<typeof createBlocksWorkbench>[0]) {
    const wb = createBlocksWorkbench(opts);
    const { init: wbInit, ...rest } = wb;
    return {
        ...rest,
        init(this: unknown): void {
            wbInit.call(this);
            initTomSelectIn(document);
        },
    };
}

function wrapChannelsInit() {
    const ch = createChannelsSettingsPage();
    const { init: chInit, ...rest } = ch;
    return {
        ...rest,
        init(this: unknown): void {
            chInit.call(this);
            initTomSelectIn(document);
        },
    };
}

function wrapPostMetaInit() {
    const page = createPostMetaPage();
    const { init: pageInit, ...rest } = page;
    return {
        ...rest,
        init(this: unknown): void {
            pageInit.call(this);
            initTomSelectIn(document);
        },
    };
}

/**
 * One `Alpine.data` per WP adminscherm houdt bootstrap simpel (geen geneste scopes die parent's
 * methods missen). Zware logika zit in losse TS-modules onder `alpine/blocks/` e.d.
 */
export function registerTeksttvAlpine(): void {
    Alpine.data('teksttvLoopPage', () => wrapWorkbenchInit({ ticker: true, groups: false, campaignAdd: false }));

    Alpine.data('teksttvCampaignsPage', () => wrapWorkbenchInit({ ticker: false, groups: true, campaignAdd: true }));

    Alpine.data('teksttvSettingsPage', () => wrapChannelsInit());

    Alpine.data('teksttvPostMetaPage', () => wrapPostMetaInit());

    Alpine.data('teksttvCategoryMedia', () => createCategoryMediaPage());
}
