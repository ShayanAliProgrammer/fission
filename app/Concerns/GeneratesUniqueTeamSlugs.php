<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Support\Str;

trait GeneratesUniqueTeamSlugs
{
    protected static function generateUniqueTeamSlug(string $name, ?string $excludeId = null): string
    {
        $defaultSlug = Str::slug($name);

        $existingSlugs = static::query()
            ->where(function ($query) use ($defaultSlug): void {
                $query->where('slug', $defaultSlug)
                    ->orWhere('slug', 'like', $defaultSlug.'-%');
            })
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->pluck('slug');

        $maxSuffix = $existingSlugs
            ->map(function (string $slug) use ($defaultSlug): ?int {
                if ($slug === $defaultSlug) {
                    return 0;
                }

                if (preg_match('/^'.preg_quote($defaultSlug, '/').'-(\d+)$/', $slug, $matches) === 1) {
                    return (int) $matches[1];
                }

                return null;
            })
            ->filter(static fn (?int $suffix): bool => $suffix !== null)
            ->max() ?? 0;

        return $existingSlugs->isEmpty()
            ? $defaultSlug
            : $defaultSlug.'-'.($maxSuffix + 1);
    }
}
