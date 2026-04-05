<?php

namespace TekstTV;

class Plugin
{
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        BuiltinBlocks::init();
        AdminPage::init();
        AuditPage::init();
        CampaignsPage::init();
        PostMeta::init();
        CategoryMeta::init();
        RestApi::init();
    }
}
