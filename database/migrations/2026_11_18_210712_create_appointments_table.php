<?php

use App\Enums\AppointmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 20)->unique(); // ياخذ الشكل DAM-2026-000154 ويتم توليده في AppointmentObserver::creating

            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();

            $table->date('appointment_date');
            $table->time('start_time');
            $table->time('end_time');

            $table->enum('status', array_column(AppointmentStatus::cases(), 'value'))
                    ->default(AppointmentStatus::Booked->value);

            $table->timestamp('reminder_sent_at')->nullable();

            $table->timestamps();

            // منع حجز نفس الوقت لنفس الطبيب
            $table->unique(['doctor_id', 'appointment_date', 'start_time'], 'uq_doctor_slot');
            // لتسريع طابور إرسال التذكيرات اليومي
            $table->index(['appointment_date', 'status', 'reminder_sent_at'], 'idx_reminders_lookup');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};