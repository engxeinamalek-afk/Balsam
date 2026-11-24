<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->time('active_slot')->nullable()->after('end_time');
        });

        DB::table('appointments')
            ->whereIn('status', ['booked', 'arrived'])
            ->update(['active_slot' => DB::raw('start_time')]);

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('uq_doctor_slot');
            $table->unique(['doctor_id', 'appointment_date', 'active_slot'], 'uq_active_doctor_slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('uq_active_doctor_slot');
            $table->dropColumn('active_slot');
            $table->unique(['doctor_id', 'appointment_date', 'start_time'], 'uq_doctor_slot');
        });
    }
};
