<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryRequest;
use App\Http\Requests\DeliveryStatusRequest;
use App\Repositories\DeliveryRepository;
use App\Services\DeliveryService;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;

class DeliveryController extends Controller
{
    public function __construct(
        private DeliveryRepository $deliveryRepository,

        private DeliveryService $deliveryService
    ){}
    public function index(): JsonResponse
    {
        $deliveries = $this->deliveryRepository->findAll();

        return ApiResponse::success(data: $deliveries);
    }

    public function store(DeliveryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $delivery = $this->deliveryService->createDelivery(deliveryValidated: $validated);

        return $delivery ? ApiResponse::success(data: $delivery) : ApiResponse::forbidden();
    }

    public function show($id): JsonResponse
    {
        $delivery = $this->deliveryRepository->findById(id: $id);

        return $delivery ? ApiResponse::success(data: $delivery) : ApiResponse::notFound();
    }

    public function updateStatus(DeliveryStatusRequest $request, $id): JsonResponse
    {
        $validated = $request->validated();
        $deliveryStatusUpdated = $this->deliveryService->updateDeliveryStatus(deliveryStatusValidated: $validated, id: $id);

        return $deliveryStatusUpdated ? ApiResponse::success() : ApiResponse::notFound();
    }

    public function destroy($id)
    {
        $delivery = $this->deliveryService->deleteDelivery(id: $id);

        return $delivery ? ApiResponse::success() : ApiResponse::notFound();
    }
}
