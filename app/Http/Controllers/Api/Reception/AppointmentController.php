<?php

namespace App\Http\Controllers\Api\Reception;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailableSlotsRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\AvailableSlotResource;
use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class AppointmentController extends Controller
{
    use ApiResponse;

    public function __construct(private AppointmentService $service) {}

    public function index(AvailableSlotsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $paginatedSlots = $this->service->getAvailableSlots(
            doctorId: (int) $validated['doctor_id'],
            branchId: (int) $validated['branch_id'],
            dateStr: $validated['date'],
            perPage: $request->integer('per_page', 10)
        );

        $resourceResponse = AvailableSlotResource::collection($paginatedSlots)
            ->response($request)
            ->getData(true);

        return $this->successResponse(
            data: $resourceResponse['data'],
            message: 'Available slots retrieved successfully.',
            additional: Arr::only($resourceResponse, ['links', 'meta']),
        );
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $appointment = $this->service->book(
            $request->validated()
        );

        return $this->successResponse(
            data: AppointmentResource::make($appointment)->resolve($request),
            message: 'Appointment booked successfully.',
            statusCode: 201,
        );
    }

    public function arrive(Appointment $appointment): JsonResponse
    {
        Gate::authorize('manageInBranch', $appointment);

        return $this->successResponse(
            data: AppointmentResource::make($this->service->arrive($appointment))->resolve(request()),
            message: 'Appointment marked as arrived successfully.',
        );
    }

    public function cancel(Appointment $appointment): JsonResponse
    {
        Gate::authorize('manageInBranch', $appointment);

        return $this->successResponse(
            data: AppointmentResource::make($this->service->cancel($appointment))->resolve(request()),
            message: 'Appointment cancelled successfully.',
        );
    }
}
