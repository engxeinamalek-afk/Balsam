<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Support\Arr;
class PatientObserver
{
    private const AUDITABLE_FIELDS = ['name', 'phone', 'email'];
    /**
     * Handle the Patient "created" event.
     */
    public function created(Patient $patient): void
    {
        //
    }

    /**
     * Handle the Patient "updated" event.
     */
    public function updated(Patient $patient): void
    {
        //تسجيل تغييرات name, phone, email فقط في audit_logs
        $changes = array_intersect_key(
            $patient->getChanges(),
            array_flip(self::AUDITABLE_FIELDS)
        );

        if (empty($changes)) {
            return;
        }

        $old = [];
        foreach (array_keys($changes) as $field) {
            $old[$field] = $patient->getOriginal($field);
        }

        AuditLog::create([
            'auditable_type' => 'patient',
            'auditable_id'   => $patient->id,
            'user_id'        => auth()->id(),
            'event'          => 'updated',
            'old_values'     => $old,
            'new_values'     => $changes,
        ]);
    }

    /**
     * Handle the Patient "deleted" event.
     */
    public function deleted(Patient $patient): void
    {
        //
    }

    /**
     * Handle the Patient "restored" event.
     */
    public function restored(Patient $patient): void
    {
        //
    }

    /**
     * Handle the Patient "force deleted" event.
     */
    public function forceDeleted(Patient $patient): void
    {
        //
    }
}
