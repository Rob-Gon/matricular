<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ViteEntriesTest extends TestCase
{
    /**
     * A file requested by @vite but not declared as an input is missing from the
     * production manifest, which makes the page fail with a 500 error.
     */
    public function test_every_vite_asset_used_in_views_exists_and_is_a_build_input(): void
    {
        $root = dirname(__DIR__, 2);

        preg_match('/input:\s*\[(.*?)\]/s', file_get_contents("$root/vite.config.js"), $input);
        preg_match_all('/[\'"](resources\/[^\'"]+)[\'"]/', $input[1] ?? '', $declared);

        $used = [];
        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$root/resources/views"));

        foreach ($views as $view) {
            if (! $view->isFile()) {
                continue;
            }

            preg_match_all('/@vite\(\[(.*?)\]\)/s', file_get_contents($view->getPathname()), $calls);
            preg_match_all('/[\'"](resources\/[^\'"]+)[\'"]/', implode(',', $calls[1]), $assets);
            $used = array_merge($used, $assets[1]);
        }

        $this->assertNotEmpty($used);

        foreach (array_unique($used) as $asset) {
            $this->assertFileExists("$root/$asset");
            $this->assertContains($asset, $declared[1], "$asset is not listed in vite.config.js inputs");
        }
    }
}
