<?php

namespace App\Console\Commands;

use App\Services\NoShowService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('appointments:mark-no-show 
            {--grace=15 : دقائق السماح بعد وقت الموعد قبل اعتباره غياباً} 
            {--branch= : رقم معرف الفرع لتصفية المواعيد لفرع محدد} 
            {--run-dry : تشغيل تجريبي لمعاينة المواعيد الفائتة دون تعديل حالتها}')]
#[Description('تحويل المواعيد الفائتة التي لم يسجل أصحابها وصولهم إلى حالة لم يحضر')]

class MarkNoShowAppointments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NoShowService $noShowService): int
    {
        // 1. محاكاة الفشل لاختبار الجدولة والتنبيهات
        if (config('app.simulate_command_failure', false)) {
            $this->error('Simulated command failure triggered via .env!');
            return self::FAILURE;
        }

        // 2. قراءة الخيارات والتحقق من صحتها
        $grace = $this->option('grace');
        $branchId = $this->option('branch');
        $isDryRun = (bool) $this->option('run-dry');

        // التحقق من المدخلات
        $validator = Validator::make([
            'grace' => $grace,
            'branch' => $branchId,
        ], [
            'grace' => ['required', 'integer', 'min:0', 'max:1440'],
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::INVALID;
        }

        // 3. تنبيه في حال وضع المحاكاة
        if ($isDryRun) {
            $this->warn('DRY-RUN MODE: Appointments will not be marked as no-show.');
        }

        // 4. استدعاء طبقة الخدمة
        $appointments = $noShowService->markOverdueAppointments(
            (int) $grace,
            $branchId ? (int) $branchId : null,
            $isDryRun
        );

        if ($appointments->isEmpty()) {
            $this->info('No overdue appointments found.');
            return self::SUCCESS;
        }

        // 5. عرض المخرجات في جدول منظم
        $rows = $appointments->map(fn ($app) => [
            $app->reference_number,
            $app->patient->name,
            $app->branch->name,
            $app->appointment_date,
            $app->start_time,
        ])->toArray();

        $this->table(['Reference', 'Patient', 'Branch', 'Date', 'Time'], $rows);

        $actionText = $isDryRun ? 'would be marked as NO-SHOW' : 'marked as NO-SHOW successfully';
        $this->info("Total {$appointments->count()} appointment(s) {$actionText}.");

        return self::SUCCESS;
    }
}
