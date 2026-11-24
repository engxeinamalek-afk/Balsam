<?php

namespace App\Listeners;

use App\Events\AppointmentCancelled;
use App\Models\Appointment;
use App\Services\SMSProvider\SMSProviderInterface;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;

#[Tries(3)]
#[Backoff([10, 60])]
#[Timeout(30)]
class SendAppointmentCancellation implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private SMSProviderInterface $sms) {}

    public function handle(AppointmentCancelled $event): void
    {
        $appointment = Appointment::with(['patient', 'doctor.user', 'branch'])
            ->findOrFail($event->appointmentId);

        $this->sms->send(
            $appointment->patient->phone,
            "تم إلغاء موعدك.\n".
            "الرقم المرجعي: {$appointment->reference_number}\n".
            "التاريخ: {$appointment->appointment_date->format('Y-m-d')} الساعة ".
            substr($appointment->start_time, 0, 5)."\n".
            "مع د. {$appointment->doctor->user->name} — {$appointment->branch->name}"
        );
    }

    public function failed(AppointmentCancelled $event, \Throwable $e): void
    {
        Log::channel('listeners')->error('Appointment cancellation SMS failed', [
            'appointment_id' => $event->appointmentId,
            'error' => $e->getMessage(),
        ]);
    }
}
