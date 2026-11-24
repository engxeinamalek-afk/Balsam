<?php

namespace App\Services;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class BranchReportService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function generateMonthlyReport(
        string $month,
        ?int $branchId = null,
        string $format = 'json',
        bool $saveFile = true
    ): array
    {
        $startOfMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $endOfMonth = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $branches = Branch::select(['id', 'name'])
            ->when($branchId, fn ($q) => $q->where('id', $branchId))
            ->get();

        $reportData = [];

        foreach ($branches as $branch) {
            // 1. إجمالي المواعيد وعدد الغيابات في هذا الفرع
            $totalAppointments = Appointment::withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $branch->id)
                ->whereBetween('appointment_date', [$startOfMonth, $endOfMonth])
                ->count();

            $noShowCount = Appointment::withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $branch->id)
                ->where('status', AppointmentStatus::NoShow)
                ->whereBetween('appointment_date', [$startOfMonth, $endOfMonth])
                ->count();

            // 2. أكثر طبيب ضغطاً في هذا الفرع خلال الشهر
            $busiestDoctor = Appointment::withoutGlobalScope(BranchScope::class)
                ->select('doctor_id', DB::raw('COUNT(*) as appointments_count'))
                ->with(['doctor:id,user_id', 'doctor.user:id,name'])
                ->where('branch_id', $branch->id)
                ->whereBetween('appointment_date', [$startOfMonth, $endOfMonth])
                ->groupBy('doctor_id')
                ->orderByDesc('appointments_count')
                ->orderBy('doctor_id')
                ->first();

            $reportData[] = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'month' => $month,
                'total_appointments' => $totalAppointments,
                'no_show_count' => $noShowCount,
                'busiest_doctor' => $busiestDoctor?->doctor?->user?->name ?? 'N/A',
                'busiest_doctor_appointments' => $busiestDoctor?->appointments_count ?? 0,
            ];
        }

        $fileName = "reports/monthly_report_{$month}" . ($branchId ? "_branch_{$branchId}" : '') . ".{$format}";

        if ($saveFile) {
            if ($format === 'csv') {
                $csvContent = $this->convertToCsv($reportData);
                Storage::disk('local')->put($fileName, $csvContent);
            } else {
                Storage::disk('local')->put($fileName, json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        return [
            'file_path' => $saveFile ? storage_path("app/{$fileName}") : null,
            'data' => $reportData,
        ];
    }

    private function convertToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($data[0]));

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
