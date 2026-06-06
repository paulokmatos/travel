<?php

namespace App\Actions;

use App\Enums\TravelOrderStatus;
use App\Models\TravelOrder;
use App\Notifications\TravelOrderStatusChanged;
use App\Services\TravelOrderCache;
use App\Support\TravelOrderStatusTransition;
use Illuminate\Support\Facades\DB;

class UpdateTravelOrderStatus
{
    public function __construct(
        private readonly TravelOrderStatusTransition $statusTransition,
        private readonly TravelOrderCache $travelOrderCache,
    ) {}

    public function execute(TravelOrder $travelOrder, TravelOrderStatus $status): TravelOrder
    {
        return DB::transaction(function () use ($travelOrder, $status): TravelOrder {
            $lockedTravelOrder = TravelOrder::query()
                ->with('user:id,name,email')
                ->whereKey($travelOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->statusTransition->assertCanTransition($lockedTravelOrder->status, $status);

            $lockedTravelOrder->update(['status' => $status]);
            $lockedTravelOrder->refresh()->load('user:id,name,email');

            DB::afterCommit(fn () => $this->travelOrderCache->bustFor($lockedTravelOrder->user));

            $lockedTravelOrder->user->notify(new TravelOrderStatusChanged($lockedTravelOrder));

            return $lockedTravelOrder;
        });
    }
}
