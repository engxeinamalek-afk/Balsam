<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Observers\AppointmentObserver;
use App\Services\SMSProvider\FakeSMSProvider;
use App\Services\SMSProvider\SMSProviderInterface;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SMSProviderInterface::class, FakeSMSProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Appointment::observe(AppointmentObserver::class);

        Relation::morphMap([
            'medical_record' => MedicalRecord::class,
            'patient' => Patient::class,
        ]);
    }
}
