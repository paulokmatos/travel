<?php

namespace App\Actions;

use App\Enums\TravelOrderStatus;
use App\Models\TravelOrder;
use App\Models\User;

class CreateTravelOrder
{
    /**
     * @param  array{destination: string, departure_date: string, return_date: string}  $data
     */
    public function execute(User $user, array $data): TravelOrder
    {
        /** @var TravelOrder $travelOrder */
        $travelOrder = $user->travelOrders()->create([
            ...$data,
            'status' => TravelOrderStatus::Solicitado,
        ]);

        return $travelOrder->load('user');
    }
}
