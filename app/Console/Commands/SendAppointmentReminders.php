<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use App\Traits\Console\Concerns\PreventsConcurrentExecution;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('appointments:send-reminders 
            {--date= : تاريخ المواعيد المستهدفة بصيغة YYYY-MM-DD (الافتراضي: غداً)} 
            {--branch= : رقم معرف الفرع لتصفية المواعيد لفرع محدد} 
            {--run-dry : تشغيل تجريبي لعرض المستلمين دون إرسال فعلي}')]
#[Description('إرسال رسائل تذكير للمرضى الذين لديهم مواعيد مؤكدة في اليوم التالي')]
class SendAppointmentReminders extends Command
{
    use PreventsConcurrentExecution;

    /**
     * Execute the console command.
     */
    public function handle(ReminderService $reminderService): int
    {
        return $this->runWithCommandLock(function () use ($reminderService): int {
            // 1. محاكاة الفشل لاختبار الجدولة والتنبيهات (عبر متغير في .env)
            if (config('app.simulate_command_failure', false)) {
                $this->error('Simulated command failure triggered via .env!');

                return self::FAILURE;
            }

            // 2. تجهيز المدخلات والتحقق من صحتها
            $date = $this->option('date') ?? now('Asia/Damascus')->addDay()->toDateString();
            $branchId = $this->option('branch');
            $isDryRun = (bool) $this->option('run-dry');

            $validator = Validator::make([
                'date' => $date,
                'branch' => $branchId,
            ], [
                'date' => ['required', 'date_format:Y-m-d'],
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
                $this->warn('DRY-RUN MODE: No SMS jobs will be dispatched and database will not be updated.');
            }

            // 4. استدعاء طبقة الخدمة
            $appointments = $reminderService->sendRemindersForDate(
                $date,
                $branchId ? (int) $branchId : null,
                $isDryRun
            );

            if ($appointments->isEmpty()) {
                $this->info("No pending reminders found for date: {$date}.");

                return self::SUCCESS;
            }

            // 5. عرض المخرجات في جدول منظم
            $rows = $appointments->map(fn ($app) => [
                $app->reference_number,
                $app->patient->name,
                $app->patient->phone,
                $app->branch->name,
                $app->start_time,
            ])->toArray();

            $this->table(['Reference', 'Patient', 'Phone', 'Branch', 'Time'], $rows);

            $actionText = $isDryRun ? 'would be queued' : 'queued successfully';
            $this->info("Total {$appointments->count()} reminder(s) {$actionText} for {$date}.");

            return self::SUCCESS;
        });
    }
}
