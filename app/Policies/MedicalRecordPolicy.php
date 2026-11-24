<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MedicalRecord;
use App\Models\User;

class MedicalRecordPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }
    public function update(User $user, MedicalRecord $record): bool
    {
        return $user->role === UserRole::Doctor
            && $user->id === $record->appointment->doctor->user_id;
    }
}
