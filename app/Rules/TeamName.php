<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Routing\Route as RouteElement;
use Illuminate\Support\Facades\Route;
use Illuminate\Translation\PotentiallyTranslatedString;

final class TeamName implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $name = mb_strtolower(mb_trim((string) $value));

        if (in_array($name, $this->reservedNames(), true)) {
            $fail('This team name is reserved and cannot be used.');
        }
    }

    /**
     * @return list<string>
     */
    private function reservedNames(): array
    {
        return once(fn (): array => collect($this->routePrefixes())
            ->merge([
                'admin',
                'api',
                'auth',
                'dashboard',
                'home',
                'login',
                'logout',
                'playground',
                'profile',
                'register',
                'settings',
                'team',
                'teams',
                'verify-email',
            ])
            ->unique()
            ->sort()
            ->values()
            ->all());
    }

    /**
     * @return list<string>
     */
    private function routePrefixes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->map(fn (RouteElement $route): string => explode('/', $route->uri)[0])
            ->reject(fn (string $uri): bool => $uri === '' || str_contains($uri, '{'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
