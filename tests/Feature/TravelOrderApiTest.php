<?php

namespace Tests\Feature;

use App\Enums\TravelOrderStatus;
use App\Models\TravelOrder;
use App\Models\User;
use App\Notifications\TravelOrderStatusChanged;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TravelOrderApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_register_login_and_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/v1/travel-orders')->assertUnauthorized();
        $this->get('/api/v1/travel-orders')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Paulo Travel',
            'email' => 'paulo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_admin' => true,
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
            ->assertJsonPath('token_type', 'bearer');

        $registeredUser = User::query()->where('email', 'paulo@example.com')->firstOrFail();

        $this->assertModelExists($registeredUser);
        $this->assertFalse($registeredUser->is_admin);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'paulo@example.com',
            'password' => 'password123',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
    }

    public function test_database_seeder_provisions_the_initial_admin_user(): void
    {
        config()->set('services.admin.name', 'Travel Admin');
        config()->set('services.admin.email', 'travel-admin@example.com');
        config()->set('services.admin.password', 'admin-password');

        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'travel-admin@example.com')->firstOrFail();

        $this->assertTrue($admin->isAdmin());

        $this->postJson('/api/v1/auth/login', [
            'email' => 'travel-admin@example.com',
            'password' => 'admin-password',
        ])->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
    }

    public function test_user_can_create_a_travel_order_and_dates_are_validated(): void
    {
        $user = User::factory()->create(['name' => 'Requester']);

        $response = $this->postJson('/api/v1/travel-orders', [
            'requester_name' => 'Spoofed Name',
            'destination' => 'Lisbon',
            'departure_date' => '2026-07-10',
            'return_date' => '2026-07-20',
        ], $this->authorizationHeader($user));

        $response
            ->assertCreated()
            ->assertJsonPath('data.requester_name', 'Requester')
            ->assertJsonPath('data.destination', 'Lisbon')
            ->assertJsonPath('data.status', TravelOrderStatus::Solicitado->value);

        $travelOrder = TravelOrder::query()->firstOrFail();

        $this->assertModelExists($travelOrder);
        $this->assertSame($user->id, $travelOrder->user_id);

        $this->postJson('/api/v1/travel-orders', [
            'destination' => 'Madrid',
            'departure_date' => '2026-07-20',
            'return_date' => '2026-07-10',
        ], $this->authorizationHeader($user))->assertInvalid(['return_date']);
    }

    public function test_users_only_see_their_orders_while_admins_see_everything(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $ownTravelOrder = TravelOrder::factory()->for($user)->create();
        $otherTravelOrder = TravelOrder::factory()->for($otherUser)->create();

        $userResponse = $this->getJson('/api/v1/travel-orders', $this->authorizationHeader($user));

        $userResponse->assertOk();
        $this->assertSame([$ownTravelOrder->id], collect($userResponse->json('data'))->pluck('id')->all());

        $this->getJson("/api/v1/travel-orders/{$otherTravelOrder->id}", $this->authorizationHeader($user))
            ->assertForbidden();

        $adminIndexResponse = $this->getJson('/api/v1/travel-orders', $this->authorizationHeader($admin));

        $adminIndexResponse->assertOk();
        $this->assertEqualsCanonicalizing(
            [$ownTravelOrder->id, $otherTravelOrder->id],
            collect($adminIndexResponse->json('data'))->pluck('id')->all(),
        );

        $this->getJson("/api/v1/travel-orders/{$otherTravelOrder->id}", $this->authorizationHeader($admin))
            ->assertOk()
            ->assertJsonPath('data.id', $otherTravelOrder->id);
    }

    public function test_travel_orders_can_be_filtered(): void
    {
        $user = User::factory()->create();

        $matchingTravelOrder = TravelOrder::factory()->for($user)->approved()->create([
            'destination' => 'Paris',
            'departure_date' => '2026-03-02',
            'return_date' => '2026-03-08',
            'created_at' => '2026-01-15 12:00:00',
            'updated_at' => '2026-01-15 12:00:00',
        ]);

        TravelOrder::factory()->for($user)->approved()->create([
            'destination' => 'Paris',
            'departure_date' => '2026-04-02',
            'return_date' => '2026-04-08',
            'created_at' => '2026-01-15 12:00:00',
            'updated_at' => '2026-01-15 12:00:00',
        ]);

        TravelOrder::factory()->for($user)->create([
            'destination' => 'Berlin',
            'departure_date' => '2026-03-02',
            'return_date' => '2026-03-08',
            'created_at' => '2026-02-15 12:00:00',
            'updated_at' => '2026-02-15 12:00:00',
        ]);

        $response = $this->getJson(
            '/api/v1/travel-orders?status=aprovado&destination=Par&created_from=2026-01-01&created_to=2026-01-31&travel_from=2026-03-01&travel_to=2026-03-10',
            $this->authorizationHeader($user),
        );

        $response->assertOk();
        $this->assertSame([$matchingTravelOrder->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_owner_can_update_only_requested_travel_orders(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $requestedTravelOrder = TravelOrder::factory()->for($user)->create([
            'destination' => 'Lisbon',
            'departure_date' => '2026-07-10',
            'return_date' => '2026-07-20',
        ]);
        $approvedTravelOrder = TravelOrder::factory()->for($user)->approved()->create();

        $response = $this->patchJson("/api/v1/travel-orders/{$requestedTravelOrder->id}", [
            'destination' => 'Porto',
            'departure_date' => '2026-08-01',
            'return_date' => '2026-08-08',
        ], $this->authorizationHeader($user));

        $response
            ->assertOk()
            ->assertJsonPath('data.destination', 'Porto')
            ->assertJsonPath('data.departure_date', '2026-08-01');

        $this->patchJson("/api/v1/travel-orders/{$requestedTravelOrder->id}", [
            'departure_date' => '2026-08-09',
        ], $this->authorizationHeader($user))->assertInvalid(['departure_date']);

        $this->patchJson("/api/v1/travel-orders/{$approvedTravelOrder->id}", [
            'destination' => 'Madrid',
        ], $this->authorizationHeader($user))->assertForbidden();

        $this->patchJson("/api/v1/travel-orders/{$requestedTravelOrder->id}", [
            'destination' => 'Madrid',
        ], $this->authorizationHeader($otherUser))->assertForbidden();
    }

    public function test_status_updates_require_an_admin_who_is_not_the_requester(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $requestingAdmin = User::factory()->admin()->create();
        $travelOrder = TravelOrder::factory()->for($user)->create();
        $adminTravelOrder = TravelOrder::factory()->for($requestingAdmin)->create();

        $this->patchJson("/api/v1/travel-orders/{$travelOrder->id}/status", [
            'status' => TravelOrderStatus::Aprovado->value,
        ], $this->authorizationHeader($user))->assertForbidden();

        $this->patchJson("/api/v1/travel-orders/{$adminTravelOrder->id}/status", [
            'status' => TravelOrderStatus::Aprovado->value,
        ], $this->authorizationHeader($requestingAdmin))->assertForbidden();

        Notification::fake();

        $response = $this->patchJson("/api/v1/travel-orders/{$travelOrder->id}/status", [
            'status' => TravelOrderStatus::Aprovado->value,
        ], $this->authorizationHeader($admin));

        $response
            ->assertOk()
            ->assertJsonPath('data.status', TravelOrderStatus::Aprovado->value);

        Notification::assertSentTo(
            $user,
            TravelOrderStatusChanged::class,
            fn (TravelOrderStatusChanged $notification, array $channels): bool => in_array('mail', $channels, true)
                && in_array('database', $channels, true)
                && $notification->travelOrder->is($travelOrder),
        );
    }

    public function test_approved_or_terminal_orders_cannot_be_canceled_or_changed(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $approvedTravelOrder = TravelOrder::factory()->for($user)->approved()->create();
        $canceledTravelOrder = TravelOrder::factory()->for($user)->canceled()->create();

        $this->patchJson("/api/v1/travel-orders/{$approvedTravelOrder->id}/status", [
            'status' => TravelOrderStatus::Cancelado->value,
        ], $this->authorizationHeader($admin))->assertStatus(409);

        $this->patchJson("/api/v1/travel-orders/{$canceledTravelOrder->id}/status", [
            'status' => TravelOrderStatus::Aprovado->value,
        ], $this->authorizationHeader($admin))->assertStatus(409);
    }

    public function test_list_cache_is_invalidated_after_travel_order_update(): void
    {
        $user = User::factory()->create();
        $travelOrder = TravelOrder::factory()->for($user)->create([
            'destination' => 'Lisbon',
            'departure_date' => '2026-07-10',
            'return_date' => '2026-07-20',
        ]);

        $this->getJson('/api/v1/travel-orders', $this->authorizationHeader($user))
            ->assertOk()
            ->assertJsonPath('data.0.destination', 'Lisbon');

        $this->patchJson("/api/v1/travel-orders/{$travelOrder->id}", [
            'destination' => 'Porto',
        ], $this->authorizationHeader($user))->assertOk();

        $this->getJson('/api/v1/travel-orders', $this->authorizationHeader($user))
            ->assertOk()
            ->assertJsonPath('data.0.destination', 'Porto');
    }

    /**
     * @return array<string, string>
     */
    private function authorizationHeader(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$this->tokenFor($user),
        ];
    }

    private function tokenFor(User $user): string
    {
        return auth('api')->login($user);
    }
}
