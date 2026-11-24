<?php

namespace App\Http\Resources;

use App\Enums\AppointmentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,
            'branch_id' => $this->branch_id,
            'appointment_date' => $this->appointment_date->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status instanceof AppointmentStatus
                ? $this->status->value
                : $this->status,
        ];
    }
}
