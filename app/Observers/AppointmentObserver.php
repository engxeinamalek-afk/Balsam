<?php

namespace App\Observers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use Illuminate\Support\Facades\Cache;

class AppointmentObserver
{
    public function creating(Appointment $appointment): void
    {
        $branch = Branch::query()
            ->lockForUpdate()
            ->findOrFail($appointment->branch_id);
        $branchCode = $branch->code;
        $year = now()->year;
        $prefix = "{$branchCode}-{$year}-";

        $lastRef = Appointment::withoutGlobalScope(BranchScope::class)
            ->where('reference_number', 'like', "{$prefix}%")
            ->orderByDesc('reference_number')
            ->value('reference_number');

        $nextSequence = $lastRef
            ? ((int) substr($lastRef, -6)) + 1
            : 1;

        $appointment->reference_number = $prefix.str_pad($nextSequence, 6, '0', STR_PAD_LEFT);
        $this->syncActiveSlot($appointment);
    }

    public function updating(Appointment $appointment): void
    {
        $this->syncActiveSlot($appointment);
    }

    private function syncActiveSlot(Appointment $appointment): void
    {
        $appointment->active_slot = in_array($appointment->status, [
            AppointmentStatus::Booked,
            AppointmentStatus::Arrived,
        ], true) ? $appointment->start_time : null;
    }

    /**
     * Handle the Appointment "created" event.
     */
    public function created(Appointment $appointment): void
    {
        // حذف الكاش لهذا الطبيب في ه1ا الفرع وهذا اليوم

        $date = $appointment->appointment_date->format('Y-m-d');
        Cache::forget("doctor:{$appointment->doctor_id}:branch:{$appointment->branch_id}:available_slots:{$date}");

    }

    /**
     * Handle the Appointment "updated" event.
     */
    public function updated(Appointment $appointment): void
    {
        // (1) مسح كاش إذا wasChanged('status')
        if ($appointment->wasChanged('status') && in_array($appointment->status, [
            AppointmentStatus::Cancelled,
            AppointmentStatus::NoShow,
        ])) {
            $date = $appointment->appointment_date->format('Y-m-d');
            Cache::forget("doctor:{$appointment->doctor_id}:branch:{$appointment->branch_id}:available_slots:{$date}");
        }
        // نحديد اخر زيارة
        if ($appointment->wasChanged('status') && $appointment->status === AppointmentStatus::Arrived) {
            $patient = $appointment->patient;
            $patient->last_visit_at = now();
            $patient->saveQuietly();
        }
        // (2) عند تغيّر status → no_show: تحديث
        if ($appointment->wasChanged('status') && $appointment->status === AppointmentStatus::NoShow) {
            $appointment->patient()->increment('no_show_count');
            $patient = $appointment->patient()->first();

            if ($patient && $patient->no_show_count >= 3) {
                $patient->banned_until = now()->addDays(30); // تأكد من مطابقة اسم العمود في جدول patients لديك
                $patient->saveQuietly();
            }
        }
        if ($appointment->wasChanged('status') && $appointment->status === AppointmentStatus::Completed) {
            // تحديث آخر زيارة للمريض بهدوء دون إطلاق أحداث إضافية
            $appointment->patient()->updateQuietly([
                'last_visit_at' => $appointment->appointment_date,
            ]);
        }

    }

    /**
     * Handle the Appointment "deleted" event.
     */
    public function deleted(Appointment $appointment): void
    {
        //	مسح كاش slot (Slot أُفرغ
        $date = $appointment->appointment_date->format('Y-m-d');
        Cache::forget("doctor:{$appointment->doctor_id}:branch:{$appointment->branch_id}:available_slots:{$date}");

    }

    /**
     * Handle the Appointment "restored" event.
     */
    public function restored(Appointment $appointment): void
    {
        //
    }

    /**
     * Handle the Appointment "force deleted" event.
     */
    public function forceDeleted(Appointment $appointment): void
    {
        //
    }
}
