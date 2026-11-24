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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->morphs('auditable');

            $table->foreignId('user_id')->nullable() // تسجيل الطبيب الذي عدل الوصفة مثلا
                  ->constrained('users')->nullOnDelete();

            $table->json('old_values'); // يتم تسجيلها في updated
            $table->json('new_values');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
