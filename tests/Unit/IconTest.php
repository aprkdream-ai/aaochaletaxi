<?php

namespace Tests\Unit;

use App\Support\Icon;
use Tests\TestCase;

class IconTest extends TestCase
{
    protected bool $seed = false;

    public function test_every_icon_referenced_in_views_and_config_exists(): void
    {
        $files = array_merge(
            glob(resource_path('views/**/*.blade.php')) ?: [],
            glob(resource_path('views/*/*/*.blade.php')) ?: [],
            glob(resource_path('views/*/*.blade.php')) ?: [],
            [config_path('navigation.php')],
        );
        $missing = [];
        foreach (array_unique($files) as $file) {
            $src = file_get_contents($file);
            preg_match_all('/(?:<x-icon\s+name|\sicon)="([a-z0-9-]+)"|\'icon\'\s*=>\s*\'([a-z0-9-]+)\'/', $src, $m);
            foreach (array_filter(array_merge($m[1], $m[2])) as $name) {
                if (! Icon::exists($name)) {
                    $missing[] = "$name (".basename($file).')';
                }
            }
        }
        $this->assertSame([], $missing, 'Run `npm run icons`.');
    }

    public function test_svg_output_is_accessible(): void
    {
        $this->assertStringContainsString('aria-hidden="true"', Icon::svg('check2'));
        $this->assertStringContainsString('role="img" aria-label="Done &amp; dusted"', Icon::svg('check2', '', 'Done & dusted'));
        $this->assertStringContainsString('class="icon text-success"', Icon::svg('check2', 'text-success'));
    }

    public function test_missing_icon_throws_outside_production(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Icon::svg('definitely-not-an-icon');
    }
}
