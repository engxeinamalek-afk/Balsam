<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMonthlyBranchReportJob;
use App\Services\BranchReportService;
use App\Traits\Console\Concerns\PreventsConcurrentExecution;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('reports:monthly 
            {--month= : الشهر المستهدف بصيغة YYYY-MM (الافتراضي: الشهر الماضي)} 
            {--branch= : رقم معرف الفرع لتوليد تقرير لفرع محدد} 
            {--format=json : صيغة الملف الناتج (json أو csv)} 
            {--sync : توليد التقرير فوراً دون إرساله إلى طابور الخلفية} 
            {--run-dry : تشغيل تجريبي لمعاينة الإحصائيات دون حفظ ملف}')]
#[Description('توليد التقرير الشهري لكل فرع وحفظه كملف JSON أو CSV في مجلد التخزين')]
class GenerateMonthlyBranchReport extends Command
{
    use PreventsConcurrentExecution;

    public function handle(BranchReportService $reportService): int
    {
        return $this->runWithCommandLock(function () use ($reportService): int {
            if (config('app.simulate_command_failure', false)) {
                $this->error('Simulated command failure triggered via .env!');

                return self::FAILURE;
            }

            $month = $this->option('month') ?? now('Asia/Damascus')->subMonth()->format('Y-m');
            $branchId = $this->option('branch');
            $format = strtolower((string) $this->option('format'));
            $isDryRun = (bool) $this->option('run-dry');
            $isSync = (bool) $this->option('sync');

            $validator = Validator::make([ // التحقق من صح المدخلات
                'month' => $month,
                'branch' => $branchId,
                'format' => $format,
            ], [
                'month' => ['required', 'date_format:Y-m'],
                'branch' => ['nullable', 'integer', 'exists:branches,id'],
                'format' => ['required', 'in:json,csv'],
            ]);

            if ($validator->fails()) { // اذا فشل التحقق
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return self::INVALID;
            }

            if ($isDryRun) { // وضع المحاكاة
                $this->warn("DRY-RUN MODE: Previewing report for {$month} without saving file.");
                $result = $reportService->generateMonthlyReport(
                    $month,
                    $branchId ? (int) $branchId : null,
                    $format,
                    false
                );
                $this->table(
                    ['Branch ID', 'Branch Name', 'Month', 'Appointments', 'No-Shows', 'Busiest Doctor', 'Doctor Appts'],
                    $result['data']
                );

                return self::SUCCESS;
            }

            if ($isSync) { // انشاء التقرير بلا جوب مباشرة من السيرفيس
                $result = $reportService->generateMonthlyReport($month, $branchId ? (int) $branchId : null, $format);
                $this->table(
                    ['Branch ID', 'Branch Name', 'Month', 'Appointments', 'No-Shows', 'Busiest Doctor', 'Doctor Appts'],
                    $result['data']
                );
                $this->info("Report generated directly at: {$result['file_path']}");

                return self::SUCCESS;
            }

            GenerateMonthlyBranchReportJob::dispatch( // جوب بعدين سيرفيس بالحالة العادية
                $month,
                $branchId ? (int) $branchId : null,
                $format
            );

            $this->info("Monthly report job for [{$month}] dispatched to [reports] queue successfully.");

            return self::SUCCESS;
        });
    }
}
