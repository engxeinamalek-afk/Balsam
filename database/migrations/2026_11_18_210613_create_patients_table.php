<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);
            $table->string('phone', 20)->unique();
            $table->string('email', 150)->nullable();

            $table->unsignedTinyInteger('no_show_count')->default(0); // يتم زيادة واحد عليه عند كل الغاء للحجز ويعود صفر عند الغاء المنع
            $table->timestamp('banned_until')->nullable()->index(); // يصبح = now()->addDays(30) عندما يغيب المريض 3 مرات
            $table->timestamp('last_visit_at')->nullable(); // يتم اعطائه قيمة now() عند حفظ الموعد باستخدام updateQuietly

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
