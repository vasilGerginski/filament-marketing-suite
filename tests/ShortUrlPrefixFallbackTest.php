<?php

declare(strict_types=1);

namespace VasilGerginski\MarketingSuite\Tests;

use VasilGerginski\MarketingSuite\Models\ShortUrl;

class ShortUrlPrefixFallbackTest extends TestCase
{
    /**
     * Simulate a host that published config/marketing-suite.php before the
     * short_urls.prefix key existed: Laravel's config merge is not recursive,
     * so the published short_urls array shadows the packaged default and the
     * prefix key comes back missing.
     */
    public function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('marketing-suite.short_urls', ['enabled' => true]);
    }

    public function test_short_links_keep_the_s_prefix_when_the_published_config_predates_the_prefix_key(): void
    {
        $this->assertSame('/s', config('short-url.prefix'));
        $this->assertStringEndsWith('/s/abc', ShortUrl::defaultShortUrlFor('abc'));
    }
}
