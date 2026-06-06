<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;

class TravelOrderCache
{
    private const int LIST_TTL_SECONDS = 60;

    private const int VERSION_TTL_SECONDS = 2_592_000;

    /**
     * @param  array{status?: string, destination?: string, created_from?: string, created_to?: string, travel_from?: string, travel_to?: string}  $filters
     * @param  Closure(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    public function rememberList(User $user, array $filters, int $perPage, int $page, Closure $callback): array
    {
        return Cache::remember(
            $this->listKey($user, $filters, $perPage, $page),
            self::LIST_TTL_SECONDS,
            $callback,
        );
    }

    public function bustFor(User $user): void
    {
        $this->incrementVersion($this->globalVersionKey());
        $this->incrementVersion($this->userVersionKey($user));
    }

    /**
     * @param  array{status?: string, destination?: string, created_from?: string, created_to?: string, travel_from?: string, travel_to?: string}  $filters
     */
    private function listKey(User $user, array $filters, int $perPage, int $page): string
    {
        ksort($filters);

        $payload = [
            'filters' => $filters,
            'page' => $page,
            'per_page' => $perPage,
            'visibility' => $user->isAdmin() ? 'admin' : "user:{$user->id}",
            'version' => $user->isAdmin()
                ? $this->version($this->globalVersionKey())
                : $this->version($this->userVersionKey($user)),
        ];

        return 'travel-orders:list:'.hash('xxh128', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function version(string $key): int
    {
        Cache::add($key, 1, self::VERSION_TTL_SECONDS);

        return (int) Cache::memo()->get($key, 1);
    }

    private function incrementVersion(string $key): void
    {
        Cache::add($key, 1, self::VERSION_TTL_SECONDS);
        Cache::increment($key);
    }

    private function globalVersionKey(): string
    {
        return 'travel-orders:version:global';
    }

    private function userVersionKey(User $user): string
    {
        return "travel-orders:version:user:{$user->id}";
    }
}
