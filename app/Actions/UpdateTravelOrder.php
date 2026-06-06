<?php

namespace App\Actions;

use App\Models\TravelOrder;
use App\Services\TravelOrderCache;

class UpdateTravelOrder
{
    public function __construct(private readonly TravelOrderCache $travelOrderCache) {}

    /**
     * @param  array{destination?: string, departure_date?: string, return_date?: string}  $data
     */
    public function execute(TravelOrder $travelOrder, array $data): TravelOrder
    {
        $travelOrder->update($data);
        $travelOrder->loadMissing('user:id,name,email');

        $this->travelOrderCache->bustFor($travelOrder->user);

        return $travelOrder->refresh()->load('user:id,name,email');
    }
}
