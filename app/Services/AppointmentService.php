<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    private const SLOT_DURATION_MINUTES = 30;

    public function book(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {

            // المريض غير ممنوع
            $patient = Patient::findOrFail($data['patient_id']);
            $this->ensurePatientNotBanned($patient);

            // الطبيب يداوم في الفرع/اليوم
            $doctor = Doctor::with('schedules')->findOrFail($data['doctor_id']);
            $date = Carbon::parse($data['appointment_date']);
            $schedule = $this->resolveSchedule($doctor, $data['branch_id'], $date);

            // الوقت داخل الدوام
            [$startTime, $endTime] = $this->computeSlot(
                $data['start_time'],
                $schedule->start_time,
                $schedule->end_time
            );

            $this->ensureSlotFree($doctor->id, $date, $startTime);

            try {
                $appointment = Appointment::create([
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'branch_id' => $data['branch_id'],
                    'appointment_date' => $date->toDateString(),
                    'start_time' => $startTime->format('H:i:s'),
                    'end_time' => $endTime->format('H:i:s'),
                    'status' => AppointmentStatus::Booked,
                    'active_slot' => $startTime->format('H:i:s'),
                ]);
            } catch (UniqueConstraintViolationException $e) {
                throw BookingException::slotAlreadyBooked([
                    'start_time' => ['هذا الموعد محجوز مسبقاً.'],
                ]);
            }

            AppointmentBooked::dispatch($appointment->id);

            return $appointment->load(['patient', 'doctor.user', 'branch']);
        });
    }

    // التحقق ان المريض مانو ممنوع من الحجز  
    private function ensurePatientNotBanned(Patient $patient): void
    {
        if ($patient->isBanned()) {
            throw BookingException::patientRestricted(['patient_id' => ['المريض محظور من الحجز بسبب تكرار الغياب.']]);
        }
    }

    // التحقق ان الطبيب يعمل بهالفرع في هاد الوقت
    private function resolveSchedule(Doctor $doctor, int $branchId, Carbon $date): DoctorSchedule
    {
        $dayOfWeek = (int) $date->format('w');

        $schedule = $doctor->schedules->first(
            fn ($s) => (int) $s->branch_id === $branchId
                    && $s->day_of_week->value === $dayOfWeek
        );

        if (! $schedule) {
            throw BookingException::doctorNotAtBranch([
                'branch_id' => ['الطبيب لا يداوم في هذا الفرع في اليوم المحدد.'],
            ]);
        }

        return $schedule;
    }

    // التحقق انو الوقت مناسب
    private function computeSlot(string $startTimeStr, string $scheduleStart, string $scheduleEnd): array
    {
        if (! preg_match('/^(?:[01]\d|2[0-3]):(?:00|30)$/', $startTimeStr)) {
            throw BookingException::invalidTimeSlot([
                'appointment_time' => ['يجب أن يبدأ الموعد على رأس الساعة أو عند الدقيقة 30.'],
            ]);
        }

        $start = Carbon::createFromFormat('H:i', $startTimeStr);
        $end = $start->copy()->addMinutes(self::SLOT_DURATION_MINUTES);

        $schedStart = Carbon::createFromTimeString($scheduleStart);
        $schedEnd = Carbon::createFromTimeString($scheduleEnd);

        if ($start->lt($schedStart) || $end->gt($schedEnd)) {
            throw BookingException::outsideWorkingHours([
                'start_time' => ['الوقت المختار يقع خارج ساعات دوام الطبيب.'],
            ]);
        }


        return [$start, $end];
    }

    private function ensureSlotFree(int $doctorId, Carbon $date, Carbon $start): void
    {
        $conflict = Appointment::query()
            ->where('doctor_id', $doctorId)
            ->whereDate('appointment_date', $date->toDateString())
            ->where('active_slot', $start->format('H:i:s'))
            ->exists();

        if ($conflict) {
            throw BookingException::slotAlreadyBooked([
                'appointment_time' => ['هذا الموعد محجوز مسبقاً لطبيب آخر أو مريض آخر.'],
            ]);
        }
    }

    public function getAvailableSlots(int $doctorId, int $branchId, string $dateStr, int $perPage = 10): LengthAwarePaginator
    {
        $cacheKey = "doctor:{$doctorId}:branch:{$branchId}:available_slots:{$dateStr}";

        $slots = Cache::remember($cacheKey, now()->addHours(6), function () use ($doctorId, $branchId, $dateStr) {
            $schedule = DoctorSchedule::where('doctor_id', $doctorId)
                ->where('branch_id', $branchId)
                ->where('day_of_week', Carbon::parse($dateStr)->dayOfWeek)
                ->first();

            if (! $schedule) {
                return [];
            }

            // توليد كل الفترات الممكنة في دوام الطبيب دفعة واحدة
            $period = CarbonPeriod::create(
                $schedule->start_time,
                self::SLOT_DURATION_MINUTES . ' minutes',
                Carbon::parse($schedule->end_time)->subMinutes(self::SLOT_DURATION_MINUTES)
            );
            $allSlots = collect($period)->map->format('H:i')->all();

            // جلب الأوقات المحجوزة
            $bookedSlots = Appointment::where('doctor_id', $doctorId)
                ->whereDate('appointment_date', $dateStr)
                ->whereNotNull('active_slot')
                ->pluck('active_slot')
                ->map(fn ($time) => substr((string) $time, 0, 5))
                ->all();

            // طرح المحجوز من الكل
            return array_values(array_diff($allSlots, $bookedSlots));
        });

        // استبعاد الأوقات التي مضت إذا كان اليوم هو الحالي
        if ($dateStr === now()->toDateString()) {
            $slots = array_values(array_filter($slots, fn ($slot) => $slot > now()->format('H:i')));
        }

        // تطبيق Pagination على المصفوفة
        $page = LengthAwarePaginator::resolveCurrentPage();
        $collection = collect($slots);

        return new LengthAwarePaginator(
            items: $collection->forPage($page, $perPage)->values(),
            total: $collection->count(),
            perPage: $perPage,
            currentPage: $page,
            options: ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }


    public function cancel(Appointment $appointment): Appointment
    {
        return DB::transaction(function () use ($appointment): Appointment {
            $appointment = Appointment::query()
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            if ($appointment->status !== AppointmentStatus::Booked) {
                throw BookingException::invalidStatusTransition([
                    'status' => ['لا يمكن إلغاء هذا الموعد في حالته الحالية.'],
                ]);
            }

            $appointment->update([
                'status' => AppointmentStatus::Cancelled,
            ]);

            AppointmentCancelled::dispatch($appointment->id);

            return $appointment->fresh(['patient', 'doctor.user', 'branch']);
        });
    }


    public function arrive(Appointment $appointment): Appointment
    {
        return DB::transaction(function () use ($appointment): Appointment {
            $appointment = Appointment::query()
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            if ($appointment->status !== AppointmentStatus::Booked) {
                throw BookingException::invalidStatusTransition([
                    'status' => ['لا يمكن تسجيل الوصول إلا لموعد في حالة محجوز.'],
                ]);
            }

            $appointment->update([
                'status' => AppointmentStatus::Arrived,
            ]);

            return $appointment->fresh(['patient', 'doctor.user', 'branch']);
        });
    }


}
