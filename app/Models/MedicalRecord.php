<?php

namespace App\Models;

use App\Observers\MedicalRecordObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[ObservedBy(MedicalRecordObserver::class)]
class MedicalRecord extends Model
{
    protected $fillable = [
        'appointment_id',
        'diagnosis',
        'prescription',
    ];
    public function appointment(): BelongsTo{
        return $this->belongsTo(Appointment::class);
    }
}
