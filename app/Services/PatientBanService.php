<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Collection;

class PatientBanService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
    public function liftExpiredBans(bool $isDryRun = false): Collection
    {
        $now = now('Asia/Damascus');

        // جلب المرضى الذين انتهت مدة حظرهم لعرضهم في الجدول
        $patients = Patient::select(['id', 'name', 'phone', 'banned_until'])
            ->whereNotNull('banned_until')
            ->where('banned_until', '<=', $now)
            ->get();

        if ($isDryRun || $patients->isEmpty()) {
            return $patients;
        }

        // تحديث جماعي سريع بسطر واحد (Mass Update) دون الحاجة لتفعيل الـ Observers
        Patient::whereIn('id', $patients->pluck('id'))
            ->update([
                'banned_until' => null,
                'no_show_count' => 0,
            ]);

        return $patients;
    }
}
