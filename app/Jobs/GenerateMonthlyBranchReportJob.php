<?php

namespace App\Jobs;

use App\Services\BranchReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(2)]
#[Timeout(1800)]
#[Backoff(120)]
class GenerateMonthlyBranchReportJob implements ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 2100;
    public function __construct(
        public readonly string $month,
        public readonly ?int $branchId = null,
        public readonly string $format = 'json'
    ) {
        $this->onQueue('reports');
    }

    public function handle(BranchReportService $reportService): void
    {
        $result = $reportService->generateMonthlyReport($this->month, $this->branchId, $this->format);
        Log::info("Monthly report generated successfully at: {$result['file_path']}");
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Failed to generate monthly report for [{$this->month}]: " . $exception->getMessage());
    }
}
