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

    public function test_filter_order_does_not_change_the_cache_key(): void
    {
        $cache = new TravelOrderCache;
        $user = $this->user(id: 10);
        $calls = 0;

        $firstPayload = $cache->rememberList($user, [
            'status' => 'aprovado',
            'destination' => 'Paris',
        ], 15, 1, function () use (&$calls): array {
            $calls++;

            return ['data' => [['id' => $calls]]];
        });

        $secondPayload = $cache->rememberList($user, [
            'destination' => 'Paris',
            'status' => 'aprovado',
        ], 15, 1, function () use (&$calls): array {
            $calls++;

            return ['data' => [['id' => $calls]]];
        });

        $this->assertSame($firstPayload, $secondPayload);
        $this->assertSame(1, $calls);
    }

    public function test_different_filters_pages_and_visibility_do_not_share_payloads(): void
    {
        $cache = new TravelOrderCache;
        $user = $this->user(id: 1);
        $adminWithSameId = $this->user(id: 1, isAdmin: true);
        $calls = 0;

        $payloads = [
            $cache->rememberList($user, ['destination' => 'Paris'], 15, 1, $this->payloadCallback($calls)),
            $cache->rememberList($user, ['destination' => 'Lisbon'], 15, 1, $this->payloadCallback($calls)),
            $cache->rememberList($user, ['destination' => 'Paris'], 10, 1, $this->payloadCallback($calls)),
            $cache->rememberList($user, ['destination' => 'Paris'], 15, 2, $this->payloadCallback($calls)),
            $cache->rememberList($adminWithSameId, ['destination' => 'Paris'], 15, 1, $this->payloadCallback($calls)),
        ];

        $this->assertSame([
            ['data' => [['id' => 1]]],
            ['data' => [['id' => 2]]],
            ['data' => [['id' => 3]]],
            ['data' => [['id' => 4]]],
            ['data' => [['id' => 5]]],
        ], $payloads);
        $this->assertSame(5, $calls);
    }

    /**
     * @return callable(): array<string, mixed>
     */
    private function payloadCallback(int &$calls): callable
    {
        return function () use (&$calls): array {
            $calls++;

            return ['data' => [['id' => $calls]]];
        };
    }

    private function user(int $id, bool $isAdmin = false): User
    {
        $user = new User(['is_admin' => $isAdmin]);
        $user->id = $id;

        return $user;
    }
}
