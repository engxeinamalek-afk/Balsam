<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class NoShowService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function markOverdueAppointments(int $graceMinutes = 15, ?int $branchId = null, bool $isDryRun = false): Collection
    {
        // حساب الحد الزمني الفاصل بتوقيت دمشق
        $cutoff = now('Asia/Damascus')->subMinutes($graceMinutes);
        $cutoffDate = $cutoff->toDateString();
        $cutoffTime = $cutoff->toTimeString();

        $query = Appointment::withoutGlobalScope(BranchScope::class)
            ->select([
                'id',
                'reference_number',
                'patient_id',
                'branch_id',
                'appointment_date',
                'start_time',
                'status',
            ])
            ->with([
                'patient:id,name,phone',
                'branch:id,name',
            ])
            ->where('status', AppointmentStatus::Booked)
            ->where(function ($q) use ($cutoffDate, $cutoffTime) {
                $q->whereDate('appointment_date', '<', $cutoffDate)
                  ->orWhere(function ($subQ) use ($cutoffDate, $cutoffTime) {
                      $subQ->whereDate('appointment_date', '=', $cutoffDate)
                           ->whereTime('start_time', '<=', $cutoffTime);
                  });
            });

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $appointments = $query->get();

        if ($isDryRun || $appointments->isEmpty()) {
            return $appointments;
        }

        // Update each row so the observer records the patient's no-show.
        foreach ($appointments as $appointment) {
            DB::transaction(function () use ($appointment): void {
                $currentAppointment = Appointment::withoutGlobalScope(BranchScope::class)
                    ->lockForUpdate()
                    ->findOrFail($appointment->id);

                if ($currentAppointment->status === AppointmentStatus::Booked) {
                    $currentAppointment->update([
                        'status' => AppointmentStatus::NoShow,
                    ]);
                }
            });

            $appointment->refresh();
        }

        return $appointments;
    }
}
