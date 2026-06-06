<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UpdateTravelOrderStatus;
use App\Enums\TravelOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexTravelOrderRequest;
use App\Http\Requests\Api\V1\StoreTravelOrderRequest;
use App\Http\Requests\Api\V1\UpdateTravelOrderRequest;
use App\Http\Requests\Api\V1\UpdateTravelOrderStatusRequest;
use App\Http\Resources\TravelOrderResource;
use App\Models\TravelOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class TravelOrderController extends Controller
{
    public function index(IndexTravelOrderRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TravelOrder::class);

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);
        unset($filters['per_page']);

        $travelOrders = TravelOrder::query()
            ->with('user')
            ->visibleTo($request->user())
            ->matchingFilters($filters)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return TravelOrderResource::collection($travelOrders);
    }

    public function store(StoreTravelOrderRequest $request): JsonResponse
    {
        $travelOrder = $request->user()->travelOrders()->create([
            ...$request->validated(),
            'status' => TravelOrderStatus::Solicitado,
        ]);

        return (new TravelOrderResource($travelOrder->load('user')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(TravelOrder $travelOrder): TravelOrderResource
    {
        Gate::authorize('view', $travelOrder);

        return new TravelOrderResource($travelOrder->load('user'));
    }

    public function update(UpdateTravelOrderRequest $request, TravelOrder $travelOrder): TravelOrderResource
    {
        $travelOrder->update($request->validated());

        return new TravelOrderResource($travelOrder->refresh()->load('user'));
    }

    public function updateStatus(
        UpdateTravelOrderStatusRequest $request,
        TravelOrder $travelOrder,
        UpdateTravelOrderStatus $updateTravelOrderStatus,
    ): TravelOrderResource {
        $travelOrder = $updateTravelOrderStatus->execute(
            $travelOrder,
            TravelOrderStatus::from($request->validated('status')),
        );

        return new TravelOrderResource($travelOrder);
    }
}
