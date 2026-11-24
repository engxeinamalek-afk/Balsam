<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Jobs\SendAppointmentReminderJob;
use App\Models\Appointment;
use Illuminate\Database\Eloquent\Collection;
class ReminderService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function sendRemindersForDate(string $date, ?int $branchId = null, bool $isDryRun = false): Collection
    {
        $query = Appointment::with(['patient', 'doctor', 'branch'])
            ->whereDate('appointment_date', $date)
            ->where('status', AppointmentStatus::Booked)
            ->whereNull('reminder_sent_at');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $appointments = $query->get();

        if ($isDryRun || $appointments->isEmpty()) {
            return $appointments;
        }

        foreach ($appointments as $appointment) {
            SendAppointmentReminderJob::dispatch($appointment->id);

            // تحديث هادئ لمنع إرسال التذكير مرتين دون تفعيل Observers غير ضرورية
            $appointment->reminder_sent_at = now();
            $appointment->saveQuietly();
        }

        return $appointments;
    }
}
