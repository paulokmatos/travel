<?php

namespace Tests\Unit;

use App\Enums\TravelOrderStatus;
use App\Models\TravelOrder;
use App\Models\User;
use App\Policies\TravelOrderPolicy;
use PHPUnit\Framework\TestCase;

class TravelOrderPolicyTest extends TestCase
{
    private TravelOrderPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new TravelOrderPolicy;
    }

    public function test_owner_can_view_and_update_requested_orders(): void
    {
        $user = $this->user(id: 1);
        $travelOrder = $this->travelOrder(userId: 1);

        $this->assertTrue($this->policy->view($user, $travelOrder));
        $this->assertTrue($this->policy->update($user, $travelOrder));
    }

    public function test_owner_cannot_update_terminal_orders(): void
    {
        $user = $this->user(id: 1);
        $travelOrder = $this->travelOrder(userId: 1, status: TravelOrderStatus::APPROVED);

        $this->assertFalse($this->policy->update($user, $travelOrder));
    }

    public function test_non_owner_cannot_view_or_update_common_user_orders(): void
    {
        $user = $this->user(id: 2);
        $travelOrder = $this->travelOrder(userId: 1);

        $this->assertFalse($this->policy->view($user, $travelOrder));
        $this->assertFalse($this->policy->update($user, $travelOrder));
    }

    public function test_admin_can_view_and_change_status_only_for_other_users_orders(): void
    {
        $admin = $this->user(id: 1, isAdmin: true);
        $otherUsersTravelOrder = $this->travelOrder(userId: 2);
        $ownTravelOrder = $this->travelOrder(userId: 1);

        $this->assertTrue($this->policy->view($admin, $otherUsersTravelOrder));
        $this->assertTrue($this->policy->updateStatus($admin, $otherUsersTravelOrder));
        $this->assertFalse($this->policy->updateStatus($admin, $ownTravelOrder));
    }

    private function user(int $id, bool $isAdmin = false): User
    {
        $user = new User(['is_admin' => $isAdmin]);
        $user->id = $id;

        return $user;
    }

    private function travelOrder(int $userId, TravelOrderStatus $status = TravelOrderStatus::REQUESTED): TravelOrder
    {
        return new TravelOrder([
            'user_id' => $userId,
            'status' => $status,
        ]);
    }
}
