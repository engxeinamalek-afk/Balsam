<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\MedicalRecord;

class MedicalRecordObserver
{
    private const AUDITABLE_FIELDS = ['diagnosis', 'prescription'];
    /**
     * Handle the MedicalRecord "created" event.
     */
    public function created(MedicalRecord $medicalRecord): void
    {
        //كتابة audit_logs بـ event = 'created' مع new_values من getAttributes() المفلترة
    }

    /**
     * Handle the MedicalRecord "updated" event.
     */
    public function updated(MedicalRecord $record): void
    {
        $changes = array_intersect_key(
            $record->getChanges(),
            array_flip(self::AUDITABLE_FIELDS)
        );

        if (empty($changes)) {
            return;
        }
        $old = [];
        foreach (array_keys($changes) as $field) {
            $old[$field] = $record->getOriginal($field);
        }
        AuditLog::create([
            'auditable_type' => 'medical_record',
            'auditable_id'   => $record->id,
            'user_id'        => auth()->id(),   // null في Command/Seeder
            'event'          => 'updated',
            'old_values'     => $old,
            'new_values'     => $changes,
        ]);
    }

    /**
     * Handle the MedicalRecord "deleted" event.
     */
    public function deleted(MedicalRecord $medicalRecord): void
    {
        //كتابة audit_logs بـ event = 'deleted' + old_values كامل
    }

    /**
     * Handle the MedicalRecord "restored" event.
     */
    public function restored(MedicalRecord $medicalRecord): void
    {
        //
    }

    /**
     * Handle the MedicalRecord "force deleted" event.
     */
    public function forceDeleted(MedicalRecord $medicalRecord): void
    {
        //
    }
}
