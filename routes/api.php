<?php

use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Doctor\AppointmentController as DoctorAppointmentController;
use App\Http\Controllers\Api\Doctor\MedicalRecordController;
use App\Http\Controllers\Api\Reception\AppointmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('reception')->middleware(['auth:sanctum', 'role:reception'])->group(function () {
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::post('/appointments/{appointment}/arrive', [AppointmentController::class, 'arrive']);
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
});

Route::prefix('doctor')->middleware(['auth:sanctum', 'role:doctor'])->group(function () {
    Route::patch('/medical-records/{record}', [MedicalRecordController::class, 'update']);
    Route::patch('/appointments/complete', [DoctorAppointmentController::class, 'complete']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
});
