<?php

namespace App\Actions;

use App\Models\TravelOrder;

class UpdateTravelOrder
{
    /**
     * @param  array{destination?: string, departure_date?: string, return_date?: string}  $data
     */
    public function execute(TravelOrder $travelOrder, array $data): TravelOrder
    {
        $travelOrder->update($data);

        return $travelOrder->refresh()->load('user');
    }
}
