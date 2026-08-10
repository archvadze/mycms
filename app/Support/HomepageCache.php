<?php

namespace App\Support;

use Closure;
use InvalidArgumentException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HomepageCache
{
    private const SECTIONS = [
        'services',
        'projects',
        'publications',
        'testimonials',
        'features',
    ];

    public static function forgetAll(): void
    {
        Cache::forget('home.page');

        foreach (self::SECTIONS as $section) {
            self::forgetSection($section);
        }
    }

    public static function forgetSection(string $section): void
    {
        self::assertValidSection($section);

        Cache::forget('home.' . $section);
        Cache::forever(self::generationKey($section), (string) Str::uuid());
    }

    public static function rememberSection(string $section, int $count, int $ttl, Closure $callback): mixed
    {
        return Cache::remember(self::sectionKey($section, $count), $ttl, $callback);
    }

    public static function sectionKey(string $section, int $count): string
    {
        self::assertValidSection($section);

        return sprintf('home.%s.%s.%d', $section, self::generation($section), $count);
    }

    private static function generation(string $section): string
    {
        return Cache::rememberForever(self::generationKey($section), fn(): string => '1');
    }

    private static function generationKey(string $section): string
    {
        return 'home.' . $section . '.generation';
    }

    private static function assertValidSection(string $section): void
    {
        if (! in_array($section, self::SECTIONS, true)) {
            throw new InvalidArgumentException("Unknown homepage cache section [{$section}].");
        }
    }
}
