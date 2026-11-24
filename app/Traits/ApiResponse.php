<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * @param  array<string, mixed>  $additional
     */
    public function successResponse(
        mixed $data = null,
        string $message = 'Operation successful.',
        int $statusCode = 200,
        array $additional = [],
    ): JsonResponse {
        return response()->json(array_merge($additional, [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]), $statusCode);
    }

    public function errorResponse(
        string $message,
        mixed $errors = null,
        int $statusCode = 400,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }
}
