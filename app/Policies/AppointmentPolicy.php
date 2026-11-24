<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\App;

class AppointmentPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }
    public function manageInBranch(User $user, Appointment $appointment): bool
    {
        return $user->role === UserRole::Reception 
            && (int) $user->branch_id === (int) $appointment->branch_id;
    }

    public function complete(User $user, Appointment $appointment):bool{
        return $user->id === $appointment->doctor_id;
    }

}
