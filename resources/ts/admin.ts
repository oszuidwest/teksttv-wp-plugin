import { initCampaignsPage } from './modules/campaigns';
import { initCategoryMeta } from './modules/category-meta';
import { initChannelsPage } from './modules/channels';
import { initLoopPage } from './modules/loop';
import { initPostMeta } from './modules/post-meta';
import { initTomSelectIn } from './modules/utils';

jQuery(() => {
    initCampaignsPage();
    initChannelsPage();
    initLoopPage();
    initTomSelectIn();
    initPostMeta();
    initCategoryMeta();
});
