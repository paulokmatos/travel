<?php

namespace App\Policies;

use App\Enums\TravelOrderStatus;
use App\Models\TravelOrder;
use App\Models\User;

class TravelOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TravelOrder $travelOrder): bool
    {
        return $user->isAdmin() || $travelOrder->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TravelOrder $travelOrder): bool
    {
        return $travelOrder->user_id === $user->id
            && $travelOrder->status === TravelOrderStatus::Solicitado;
    }

    public function updateStatus(User $user, TravelOrder $travelOrder): bool
    {
        return $user->isAdmin() && $travelOrder->user_id !== $user->id;
    }

    public function delete(User $user, TravelOrder $travelOrder): bool
    {
        return false;
    }

    public function restore(User $user, TravelOrder $travelOrder): bool
    {
        return false;
    }

    public function forceDelete(User $user, TravelOrder $travelOrder): bool
    {
        return false;
    }
}
