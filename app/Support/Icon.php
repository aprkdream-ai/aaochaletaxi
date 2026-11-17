<?php

namespace App\Support;

/**
 * Inline SVG icons (Bootstrap Icons, MIT) copied into resources/icons by `npm run icons`.
 * Inline SVG avoids downloading a 130 KB icon font.
 */
class Icon
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function svg(string $name, string $class = '', ?string $label = null): string
    {
        $inner = self::$cache[$name] ??= self::load($name);

        if ($inner === '') {
            return '';
        }

        $a11y = $label !== null
            ? 'role="img" aria-label="'.e($label).'"'
            : 'aria-hidden="true" focusable="false"';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="icon '.e($class).'" '.$a11y.'>'.$inner.'</svg>';
    }

    public static function exists(string $name): bool
    {
        return is_file(self::path($name));
    }

    public static function path(string $name): string
    {
        return resource_path('icons/'.basename($name).'.svg');
    }

    private static function load(string $name): string
    {
        $file = self::path($name);

        if (! is_file($file)) {
            if (! app()->isProduction()) {
                throw new \InvalidArgumentException("Icon [{$name}] not found. Run `npm run icons`.");
            }

            return '';
        }

        // Keep only the inner shapes of the source SVG.
        return trim(preg_replace('/^.*?<svg[^>]*>|<\/svg>\s*$/s', '', file_get_contents($file)));
    }
}
