<?php

namespace App\Models;

use App\Enums\TravelOrderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TravelOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'destination', 'departure_date', 'return_date', 'status'])]
class TravelOrder extends Model
{
    /** @use HasFactory<TravelOrderFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<TravelOrder>  $query
     * @return Builder<TravelOrder>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereBelongsTo($user);
    }

    /**
     * @param  Builder<TravelOrder>  $query
     * @param  array{status?: string, destination?: string, created_from?: string, created_to?: string, travel_from?: string, travel_to?: string}  $filters
     * @return Builder<TravelOrder>
     */
    public function scopeMatchingFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['destination'] ?? null, fn (Builder $query, string $destination): Builder => $query->where('destination', 'like', "%{$destination}%"))
            ->when($filters['created_from'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '>=', CarbonImmutable::parse($date)->startOfDay()))
            ->when($filters['created_to'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '<=', CarbonImmutable::parse($date)->endOfDay()))
            ->when($filters['travel_from'] ?? null, fn (Builder $query, string $date): Builder => $query->where('departure_date', '>=', $date))
            ->when($filters['travel_to'] ?? null, fn (Builder $query, string $date): Builder => $query->where('return_date', '<=', $date));
    }

    protected function casts(): array
    {
        return [
            'departure_date' => 'date:Y-m-d',
            'return_date' => 'date:Y-m-d',
            'status' => TravelOrderStatus::class,
        ];
    }
}
