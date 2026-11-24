<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse;
    public function index(){
        $logs= AuditLog::latest()->paginete(10);
        return $this->successResponse(message:"Logs retrieved successfully!", data: AuditLogResource::collection($logs));
    }
}
