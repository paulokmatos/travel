<?php

namespace App\Actions;

use App\Enums\TravelOrderStatus;
use App\Models\TravelOrder;
use App\Models\User;
use App\Services\TravelOrderCache;

class CreateTravelOrder
{
    public function __construct(private readonly TravelOrderCache $travelOrderCache) {}

    /**
     * @param  array{destination: string, departure_date: string, return_date: string}  $data
     */
    public function execute(User $user, array $data): TravelOrder
    {
        /** @var TravelOrder $travelOrder */
        $travelOrder = $user->travelOrders()->create([
            ...$data,
            'status' => TravelOrderStatus::REQUESTED,
        ]);

        $this->travelOrderCache->bustFor($user);

        return $travelOrder->load('user:id,name,email');
    }
}
