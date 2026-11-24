<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Enums\Specialty;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class ClinicScenarioSeeder extends Seeder
{
    public const ADMIN_TOKEN = 'balsam-local-admin-test-token';

    public const RECEPTION_DAMASCUS_TOKEN = 'balsam-local-damascus-reception-token';

    public const RECEPTION_ALEPPO_TOKEN = 'balsam-local-aleppo-reception-token';

    public const DOCTOR_AMINA_TOKEN = 'balsam-local-amina-doctor-token';

    public const DOCTOR_BASIL_TOKEN = 'balsam-local-basil-doctor-token';

    public const APPOINTMENT_DATE = '2026-11-25';

    public const OVERDUE_APPOINTMENT_DATE = '2026-11-23';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Clinic scenario data may only be seeded in local or testing environments.');
        }

        Model::withoutEvents(function (): void {
            DB::transaction(function (): void {
                $this->seedBranches();
                $users = $this->seedUsers();
                $doctors = $this->seedDoctors($users);
                $branches = Branch::query()->get()->keyBy('code');

                $this->seedSchedules($doctors, $branches);
                $patients = $this->seedPatients();
                $appointments = $this->seedAppointments($doctors, $branches, $patients);
                $this->seedMedicalRecord($appointments['completed'], $users['doctor.amina@balsam.test']);
                $this->seedAuditLog($appointments['completed'], $users['doctor.amina@balsam.test']);
                $this->seedTokens($users);
            });
        });
    }

    private function seedBranches(): void
    {
        foreach ([
            ['code' => 'DAM', 'name' => 'Damascus'],
            ['code' => 'ALE', 'name' => 'Aleppo'],
            ['code' => 'LAT', 'name' => 'Latakia'],
        ] as $branch) {
            Branch::updateOrCreate(
                ['code' => $branch['code']],
                ['name' => $branch['name']],
            );
        }
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(): array
    {
        $branches = Branch::query()->get()->keyBy('code');

        $accounts = [
            'admin@balsam.test' => [
                'name' => 'Balsam Test Admin',
                'role' => UserRole::Admin,
                'branch_id' => null,
            ],
            'reception.damascus@balsam.test' => [
                'name' => 'Damascus Reception',
                'role' => UserRole::Reception,
                'branch_id' => $branches['DAM']->id,
            ],
            'reception.aleppo@balsam.test' => [
                'name' => 'Aleppo Reception',
                'role' => UserRole::Reception,
                'branch_id' => $branches['ALE']->id,
            ],
            'doctor.amina@balsam.test' => [
                'name' => 'Dr. Amina Test',
                'role' => UserRole::Doctor,
                'branch_id' => null,
            ],
            'doctor.basil@balsam.test' => [
                'name' => 'Dr. Basil Test',
                'role' => UserRole::Doctor,
                'branch_id' => null,
            ],
        ];

        $users = [];

        foreach ($accounts as $email => $account) {
            $users[$email] = User::updateOrCreate(
                ['email' => $email],
                [
                    ...$account,
                    'email' => $email,
                    'password' => 'password',
                ],
            );
        }

        return $users;
    }

    /**
     * @param  array<string, User>  $users
     * @return array<string, Doctor>
     */
    private function seedDoctors(array $users): array
    {
        return [
            'amina' => Doctor::updateOrCreate(
                ['user_id' => $users['doctor.amina@balsam.test']->id],
                ['specialty' => Specialty::Cardiology],
            ),
            'basil' => Doctor::updateOrCreate(
                ['user_id' => $users['doctor.basil@balsam.test']->id],
                ['specialty' => Specialty::General],
            ),
        ];
    }

    /**
     * @param  array<string, Doctor>  $doctors
     * @param  Collection<string, Branch>  $branches
     */
    private function seedSchedules(array $doctors, Collection $branches): void
    {
        $schedules = [
            [$doctors['amina'], $branches['DAM'], DayOfWeek::Monday, '09:00:00', '11:00:00'],
            [$doctors['amina'], $branches['DAM'], DayOfWeek::Wednesday, '09:00:00', '11:00:00'],
            [$doctors['amina'], $branches['ALE'], DayOfWeek::Thursday, '10:00:00', '12:00:00'],
            [$doctors['basil'], $branches['LAT'], DayOfWeek::Wednesday, '09:00:00', '10:30:00'],
        ];

        foreach ($schedules as [$doctor, $branch, $dayOfWeek, $startTime, $endTime]) {
            DoctorSchedule::updateOrCreate(
                [
                    'doctor_id' => $doctor->id,
                    'branch_id' => $branch->id,
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $startTime,
                ],
                ['end_time' => $endTime],
            );
        }
    }

    /**
     * @return array<string, Patient>
     */
    private function seedPatients(): array
    {
        $patients = [
            'active' => [
                'name' => 'Test Patient Active',
                'phone' => '+963900000001',
                'email' => 'patient.active@balsam.test',
                'no_show_count' => 0,
                'banned_until' => null,
            ],
            'two-no-shows' => [
                'name' => 'Test Patient Two No-Shows',
                'phone' => '+963900000002',
                'email' => 'patient.two-no-shows@balsam.test',
                'no_show_count' => 2,
                'banned_until' => null,
            ],
            'expired-ban' => [
                'name' => 'Test Patient Expired Ban',
                'phone' => '+963900000003',
                'email' => 'patient.expired-ban@balsam.test',
                'no_show_count' => 3,
                'banned_until' => '2026-11-20 23:59:59',
            ],
            'active-ban' => [
                'name' => 'Test Patient Active Ban',
                'phone' => '+963900000004',
                'email' => 'patient.active-ban@balsam.test',
                'no_show_count' => 3,
                'banned_until' => '2026-12-01 23:59:59',
            ],
        ];

        $seededPatients = [];

        foreach ($patients as $key => $patient) {
            $seededPatients[$key] = Patient::updateOrCreate(
                ['phone' => $patient['phone']],
                $patient,
            );
        }

        return $seededPatients;
    }

    /**
     * @param  array<string, Doctor>  $doctors
     * @param  Collection<string, Branch>  $branches
     * @param  array<string, Patient>  $patients
     * @return array<string, Appointment>
     */
    private function seedAppointments(array $doctors, Collection $branches, array $patients): array
    {
        return [
            'tomorrow-damascus' => $this->seedAppointment(
                'DAM-2026-900001',
                $patients['active'],
                $doctors['amina'],
                $branches['DAM'],
                self::APPOINTMENT_DATE,
                '09:00:00',
                AppointmentStatus::Booked,
            ),
            'tomorrow-latakia' => $this->seedAppointment(
                'LAT-2026-900001',
                $patients['active'],
                $doctors['basil'],
                $branches['LAT'],
                self::APPOINTMENT_DATE,
                '09:00:00',
                AppointmentStatus::Booked,
            ),
            'tomorrow-aleppo' => $this->seedAppointment(
                'ALE-2026-900001',
                $patients['active'],
                $doctors['amina'],
                $branches['ALE'],
                '2026-11-26',
                '10:00:00',
                AppointmentStatus::Booked,
            ),
            'arrived' => $this->seedAppointment(
                'DAM-2026-900002',
                $patients['active'],
                $doctors['amina'],
                $branches['DAM'],
                '2026-11-18',
                '09:00:00',
                AppointmentStatus::Arrived,
            ),
            'completed' => $this->seedAppointment(
                'DAM-2026-900003',
                $patients['active'],
                $doctors['amina'],
                $branches['DAM'],
                '2026-11-18',
                '10:00:00',
                AppointmentStatus::Completed,
            ),
            'cancelled' => $this->seedAppointment(
                'DAM-2026-900004',
                $patients['active'],
                $doctors['amina'],
                $branches['DAM'],
                '2026-11-16',
                '09:00:00',
                AppointmentStatus::Cancelled,
            ),
            'first-no-show' => $this->seedAppointment(
                'DAM-2026-900005',
                $patients['two-no-shows'],
                $doctors['amina'],
                $branches['DAM'],
                '2026-11-04',
                '09:00:00',
                AppointmentStatus::NoShow,
            ),
            'second-no-show' => $this->seedAppointment(
                'DAM-2026-900006',
                $patients['two-no-shows'],
                $doctors['amina'],
                $branches['DAM'],
                '2026-11-11',
                '09:00:00',
                AppointmentStatus::NoShow,
            ),
            'overdue' => $this->seedAppointment(
                'DAM-2026-900007',
                $patients['two-no-shows'],
                $doctors['amina'],
                $branches['DAM'],
                self::OVERDUE_APPOINTMENT_DATE,
                '09:00:00',
                AppointmentStatus::Booked,
            ),
        ];
    }

    private function seedAppointment(
        string $referenceNumber,
        Patient $patient,
        Doctor $doctor,
        Branch $branch,
        string $date,
        string $startTime,
        AppointmentStatus $status,
    ): Appointment {
        $endTime = match ($startTime) {
            '09:00:00' => '09:30:00',
            '10:00:00' => '10:30:00',
            default => throw new LogicException("Unsupported seeded appointment start time: {$startTime}"),
        };

        return Appointment::updateOrCreate(
            ['reference_number' => $referenceNumber],
            [
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'branch_id' => $branch->id,
                'appointment_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'active_slot' => in_array($status, [
                    AppointmentStatus::Booked,
                    AppointmentStatus::Arrived,
                ], true) ? $startTime : null,
                'status' => $status,
            ],
        );
    }

    private function seedMedicalRecord(Appointment $appointment, User $doctorUser): void
    {
        $record = MedicalRecord::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'diagnosis' => 'Seeded diagnosis for the completed appointment.',
                'prescription' => 'Seeded test prescription.',
            ],
        );

        AuditLog::updateOrCreate(
            [
                'auditable_type' => 'medical_record',
                'auditable_id' => $record->id,
                'user_id' => $doctorUser->id,
            ],
            [
                'old_values' => ['diagnosis' => 'Initial diagnosis'],
                'new_values' => ['diagnosis' => 'Seeded diagnosis for the completed appointment.'],
            ],
        );
    }

    private function seedAuditLog(Appointment $appointment, User $doctorUser): void
    {
        AuditLog::updateOrCreate(
            [
                'auditable_type' => 'patient',
                'auditable_id' => $appointment->patient_id,
                'user_id' => $doctorUser->id,
            ],
            [
                'old_values' => ['email' => 'old.patient@balsam.test'],
                'new_values' => ['email' => 'patient.active@balsam.test'],
            ],
        );
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedTokens(array $users): void
    {
        $tokens = [
            'admin@balsam.test' => self::ADMIN_TOKEN,
            'reception.damascus@balsam.test' => self::RECEPTION_DAMASCUS_TOKEN,
            'reception.aleppo@balsam.test' => self::RECEPTION_ALEPPO_TOKEN,
            'doctor.amina@balsam.test' => self::DOCTOR_AMINA_TOKEN,
            'doctor.basil@balsam.test' => self::DOCTOR_BASIL_TOKEN,
        ];

        foreach ($tokens as $email => $plainTextToken) {
            $users[$email]->tokens()->updateOrCreate(
                ['name' => 'clinic-scenario-test-token'],
                [
                    'token' => hash('sha256', $plainTextToken),
                    'abilities' => ['*'],
                    'expires_at' => null,
                ],
            );
        }
    }
}
