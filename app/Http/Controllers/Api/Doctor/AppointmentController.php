<?php

namespace App\Http\Controllers\Api\Doctor;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Gate;
class AppointmentController extends Controller
{
    use ApiResponse;
    public function complete(Appointment $appointment){
        // بوليسي للتأكد انو هالموعد لهاد الدكتور
        Gate::authorize('complete', $appointment);
        // تسجيل انو الموعد خلص
        $appointment->update([
            'status' => AppointmentStatus::Completed,
        ]);

        return $this->successResponse(message:"Appointment completed successfully!",
                                        data:new AppointmentResource($appointment->fresh()));
    }
}
