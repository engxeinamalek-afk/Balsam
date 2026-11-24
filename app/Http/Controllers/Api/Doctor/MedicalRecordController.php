<?php

namespace App\Http\Controllers\Api\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMedicalRecordRequest;
use App\Http\Resources\MedicalRecordResource;
use App\Models\MedicalRecord;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class MedicalRecordController extends Controller
{
    use ApiResponse;

    public function store()
    {
        // مع بوليسي للتأكد انو هالموعد الو
        // تسجيل التشخيص والحالة
    }

    public function update(MedicalRecord $record, UpdateMedicalRecordRequest $request): JsonResponse
    {
        $record->update($request->validated());

        return $this->successResponse(
            data: MedicalRecordResource::make($record->refresh())->resolve($request),
            message: 'Medical record updated successfully.',
        );
    }
}
