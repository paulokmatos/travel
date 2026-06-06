<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\TravelOrderCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TravelOrderCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_user_list_payload_is_cached_until_owner_version_is_busted(): void
    {
        $cache = new TravelOrderCache;
        $user = $this->user(id: 10);
        $calls = 0;

        $firstPayload = $cache->rememberList($user, ['destination' => 'Paris'], 15, 1, function () use (&$calls): array {
            $calls++;

            return ['data' => [['id' => $calls]]];
        });

        $secondPayload = $cache->rememberList($user, ['destination' => 'Paris'], 15, 1, function () use (&$calls): array {
            $calls++;

            return ['data' => [['id' => $calls]]];
        });

        $cache->bustFor($user);

        $thirdPayload = $cache->rememberList($user, ['destination' => 'Paris'], 15, 1, function () use (&$calls): array {
            $calls++;

            return ['data' => [['id' => $calls]]];
        });

        $this->assertSame(['data' => [['id' => 1]]], $firstPayload);
        $this->assertSame($firstPayload, $secondPayload);
        $this->assertSame(['data' => [['id' => 2]]], $thirdPayload);
        $this->assertSame(2, $calls);
    }

    public function test_admin_list_payload_uses_global_version(): void
    {
        $cache = new TravelOrderCache;
        $admin = $this->user(id: 1, isAdmin: true);
        $owner = $this->user(id: 2);
        $calls = 0;

        $cache->rememberList($admin, [], 15, 1, function () use (&$calls): array {
            $calls++;

            return ['data' => [['id' => $calls]]];
        });

        $cache->bustFor($owner);

        $payload = $cache->rememberList($admin, [], 15, 1, function () use (&$calls): array {
            $calls++;

            return ['data' => [['id' => $calls]]];
        });

        $this->assertSame(['data' => [['id' => 2]]], $payload);
        $this->assertSame(2, $calls);
    }

    private function user(int $id, bool $isAdmin = false): User
    {
        $user = new User(['is_admin' => $isAdmin]);
        $user->id = $id;

        return $user;
    }
}
