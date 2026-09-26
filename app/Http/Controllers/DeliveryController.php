<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryAssignRequest;
use App\Http\Requests\DeliveryRequest;
use App\Http\Requests\DeliveryStatusRequest;
use App\Services\DeliveryService;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        private DeliveryService $deliveryService
    ){}

    public function index(Request $request): JsonResponse
    {
        $deliveries = $this->deliveryService->listDeliveries(
            perPage: (int) $request->query('per_page', 10),
            search: $request->query('search')
        );

        return ApiResponse::paginated($deliveries);
    }

    public function store(DeliveryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $delivery = $this->deliveryService->createDelivery(deliveryValidated: $validated);

        return $delivery ? ApiResponse::success(data: $delivery) : ApiResponse::forbidden();
    }

    public function show($id): JsonResponse
    {
        $delivery = $this->deliveryService->findVisibleDelivery(id: $id);

        return $delivery ? ApiResponse::success(data: $delivery) : ApiResponse::notFound();
    }

    public function updateStatus(DeliveryStatusRequest $request, $id): JsonResponse
    {
        $validated = $request->validated();

        $delivery = $this->deliveryService->updateDeliveryStatus(deliveryStatusValidated: $validated, id: $id);

        return $delivery ? ApiResponse::success(data: $delivery) : ApiResponse::notFound();
    }

    public function cancel($id): JsonResponse
    {
        $delivery = $this->deliveryService->cancelDelivery(id: $id);

        return $delivery ? ApiResponse::success(data: $delivery) : ApiResponse::notFound();
    }

    public function attach($id): JsonResponse
    {
        $delivery = $this->deliveryService->attachDelivery(id: $id);

        return $delivery ? ApiResponse::success(data: $delivery) : ApiResponse::notFound();
    }

    public function detach($id): JsonResponse
    {
        $delivery = $this->deliveryService->detachDelivery(id: $id);

        return $delivery ? ApiResponse::success(data: $delivery) : ApiResponse::notFound();
    }

    public function assign(DeliveryAssignRequest $request, $id): JsonResponse
    {
        $validated = $request->validated();

        $delivery = $this->deliveryService->assignDeliveryman(id: $id, deliverymanId: $validated['delivery_man_id']);

        return $delivery ? ApiResponse::success(data: $delivery) : ApiResponse::notFound();
    }

    public function destroy($id): JsonResponse
    {
        $delivery = $this->deliveryService->deleteDelivery(id: $id);

        return $delivery ? ApiResponse::success() : ApiResponse::notFound();
    }
}
