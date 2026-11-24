<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class BaseApiException extends Exception
{
    /**
     * كود حالة HTTP الافتراضي
     */
    protected int $statusCode = 400;

    /**
     * مصفوفة تفاصيل الأخطاء
     */
    protected array $errors = [];

    public function __construct(string $message = '', array $errors = [], ?int $statusCode = null)
    {
        $this->errors = $errors;

        if ($statusCode !== null) {
            $this->statusCode = $statusCode;
        }

        parent::__construct($message, $this->statusCode);
    }

    /**
     * جلب مصفوفة الأخطاء
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * جلب كود الحالة
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => $this->getErrors(),
        ], $this->statusCode);
    }
}
