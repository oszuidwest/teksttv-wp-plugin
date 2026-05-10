<?php

namespace TekstTV;

use TekstTV\Blocks\Loop\ArticlesLoopBlock;
use TekstTV\Blocks\Loop\CampaignLoopBlock;
use TekstTV\Blocks\Loop\ImageLoopBlock;
use TekstTV\Blocks\Loop\WeatherLoopBlock;
use TekstTV\Blocks\Ticker\TickerHeadlinesBlock;
use TekstTV\Blocks\Ticker\TickerTextBlock;

/**
 * Registers built-in loop and ticker block types (TekstTV\Blocks\Loop and TekstTV\Blocks\Ticker).
 */
class BuiltinBlocks
{
    public static function init(): void
    {
        add_action('init', [self::class, 'register'], 5);
    }

    public static function register(): void
    {
        ArticlesLoopBlock::register();
        ImageLoopBlock::register();
        CampaignLoopBlock::register();
        WeatherLoopBlock::register();
        TickerTextBlock::register();
        TickerHeadlinesBlock::register();
    }
}
