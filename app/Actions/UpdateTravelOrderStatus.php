<?php

namespace App\Actions;

use App\Enums\TravelOrderStatus;
use App\Models\TravelOrder;
use App\Notifications\TravelOrderStatusChanged;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdateTravelOrderStatus
{
    public function execute(TravelOrder $travelOrder, TravelOrderStatus $status): TravelOrder
    {
        return DB::transaction(function () use ($travelOrder, $status): TravelOrder {
            $lockedTravelOrder = TravelOrder::query()
                ->with('user')
                ->whereKey($travelOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTravelOrder->status !== TravelOrderStatus::Solicitado) {
                throw new ConflictHttpException('Only requested travel orders can be approved or canceled.');
            }

            $lockedTravelOrder->update(['status' => $status]);
            $lockedTravelOrder->refresh()->load('user');

            $lockedTravelOrder->user->notify(new TravelOrderStatusChanged($lockedTravelOrder));

            return $lockedTravelOrder;
        });
    }
}
