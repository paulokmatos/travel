<?php

namespace App\Queries;

use App\Models\TravelOrder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TravelOrderQuery
{
    /**
     * @param  array{status?: string, destination?: string, created_from?: string, created_to?: string, travel_from?: string, travel_to?: string}  $filters
     * @return LengthAwarePaginator<int, TravelOrder>
     */
    public function paginate(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->base($user, $filters)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{status?: string, destination?: string, created_from?: string, created_to?: string, travel_from?: string, travel_to?: string}  $filters
     * @return Builder<TravelOrder>
     */
    public function base(User $user, array $filters): Builder
    {
        return TravelOrder::query()
            ->select(['id', 'user_id', 'destination', 'departure_date', 'return_date', 'status', 'created_at', 'updated_at'])
            ->with('user:id,name,email')
            ->visibleTo($user)
            ->matchingFilters($filters);
    }
}
