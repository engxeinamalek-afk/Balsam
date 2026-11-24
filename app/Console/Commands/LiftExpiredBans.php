<?php

namespace App\Console\Commands;

use App\Services\PatientBanService;
use App\Traits\Console\Concerns\PreventsConcurrentExecution;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('patients:lift-bans 
            {--run-dry : تشغيل تجريبي لعرض المرضى المستحقين لرفع المنع دون تعديل قاعدة البيانات}')]
#[Description('رفع المنع تلقائياً عن المرضى الذين انتهت مدة حظرهم (30 يوماً)')]

class LiftExpiredBans extends Command
{
    use PreventsConcurrentExecution;

    /**
     * Execute the console command.
     */
    public function handle(PatientBanService $patientBanService): int
    {
        return $this->runWithCommandLock(function () use ($patientBanService): int {
            // 1. محاكاة الفشل لاختبار الجدولة والتنبيهات
            if (config('app.simulate_command_failure', false)) {
                $this->error('Simulated command failure triggered via .env!');

                return self::FAILURE;
            }

            $isDryRun = (bool) $this->option('run-dry');

            // 2. تنبيه في حال وضع المحاكاة
            if ($isDryRun) {
                $this->warn('DRY-RUN MODE: Bans will not be lifted in the database.');
            }

            // 3. استدعاء طبقة الخدمة
            $patients = $patientBanService->liftExpiredBans($isDryRun);

            if ($patients->isEmpty()) {
                $this->info('No expired patient bans found.');

                return self::SUCCESS;
            }

            // 4. عرض المخرجات في جدول منظم
            $rows = $patients->map(fn ($patient) => [
                $patient->id,
                $patient->name,
                $patient->phone,
                $patient->banned_until,
            ])->toArray();

            $this->table(['ID', 'Patient Name', 'Phone', 'Banned Until'], $rows);

            $actionText = $isDryRun ? 'would have their ban lifted' : 'had their ban lifted successfully';
            $this->info("Total {$patients->count()} patient(s) {$actionText}.");

            return self::SUCCESS;
        });
    }
}
