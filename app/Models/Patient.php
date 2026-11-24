<?php

namespace App\Models;

use App\Observers\PatientObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
#[ObservedBy(PatientObserver::class)]
class Patient extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'no_show_count',
        'banned_until',
        'last_visit_at',
    ];
    
    protected function casts(): array
    {
        return [
            'no_show_count' => 'integer',
            'banned_until'  => 'datetime',
            'last_visit_at' => 'datetime',
        ];
    }

    public function isBanned(): bool
    {
        return $this->banned_until !== null
            && $this->banned_until->isFuture();
    }
}
