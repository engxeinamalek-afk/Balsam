<?php

namespace App\Jobs;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Scopes\BranchScope;
use App\Services\SMSProvider\SMSProviderInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Throwable;
#[Tries(3)]
#[Backoff([10, 60])]
#[Timeout(30)]

class SendAppointmentReminderJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $appointmentId)
    {
        $this->onQueue('reminders');
    }

    /**
     * Execute the job.
     */
    public function handle(SMSProviderInterface $smsProvider): void
    {
        $appointment = Appointment::withoutGlobalScope(BranchScope::class)
            ->select([
                'id',
                'patient_id',
                'doctor_id',
                'branch_id',
                'appointment_date',
                'start_time',
                'status',
            ])
            ->with([
                'patient:id,name,phone',
                'doctor:id,user_id',
                'doctor.user:id,name',
                'branch:id,name',
            ])
            ->find($this->appointmentId);
        if (! $appointment || $appointment->status !== AppointmentStatus::Booked) {
            return;
        }

        $message = "تذكير من عيادات بلسم: لديك موعد غداً ({$appointment->appointment_date->format('Y-m-d')}) الساعة {$appointment->start_time} مع د. {$appointment->doctor->user->name} في فرع {$appointment->branch->name}.";

        $smsProvider->send($appointment->patient->phone, $message);
    }
    public function failed(Throwable $exception): void
    {
        Log::error("Failed to send reminder SMS for appointment ID [{$this->appointmentId}]: " . $exception->getMessage());
    }
}
