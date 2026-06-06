<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateTravelOrder;
use App\Actions\UpdateTravelOrder;
use App\Actions\UpdateTravelOrderStatus;
use App\Enums\TravelOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexTravelOrderRequest;
use App\Http\Requests\Api\V1\StoreTravelOrderRequest;
use App\Http\Requests\Api\V1\UpdateTravelOrderRequest;
use App\Http\Requests\Api\V1\UpdateTravelOrderStatusRequest;
use App\Http\Resources\TravelOrderResource;
use App\Models\TravelOrder;
use App\Queries\TravelOrderQuery;
use App\Services\TravelOrderCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class TravelOrderController extends Controller
{
    public function index(
        IndexTravelOrderRequest $request,
        TravelOrderQuery $travelOrderQuery,
        TravelOrderCache $travelOrderCache,
    ): JsonResponse {
        Gate::authorize('viewAny', TravelOrder::class);

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = max(1, $request->integer('page', 1));
        unset($filters['page'], $filters['per_page']);

        $payload = $travelOrderCache->rememberList(
            $request->user(),
            $filters,
            $perPage,
            $page,
            function () use ($travelOrderQuery, $request, $filters, $perPage): array {
                $travelOrders = $travelOrderQuery->paginate($request->user(), $filters, $perPage);

                return TravelOrderResource::collection($travelOrders)
                    ->response()
                    ->getData(true);
            },
        );

        return response()->json($payload);
    }

    public function store(StoreTravelOrderRequest $request, CreateTravelOrder $createTravelOrder): JsonResponse
    {
        $travelOrder = $createTravelOrder->execute($request->user(), $request->validated());

        return (new TravelOrderResource($travelOrder))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(TravelOrder $travelOrder): TravelOrderResource
    {
        Gate::authorize('view', $travelOrder);

        return new TravelOrderResource($travelOrder->load('user:id,name,email'));
    }

    public function update(
        UpdateTravelOrderRequest $request,
        TravelOrder $travelOrder,
        UpdateTravelOrder $updateTravelOrder,
    ): TravelOrderResource {
        return new TravelOrderResource($updateTravelOrder->execute($travelOrder, $request->validated()));
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
