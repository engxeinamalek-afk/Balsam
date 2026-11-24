<?php

namespace App\Exceptions;

class BookingException extends BaseApiException
{
    public static function patientRestricted(array $errors = []): self
    {
        return new self(
            message: 'عذراً، هذا المريض ممنوع من إجراء حجوزات جديدة.',
            errors: $errors,
            statusCode: 403
        );
    }

    public static function doctorNotAtBranch(array $errors = []): self
    {
        return new self(
            message: 'الطبيب غير متواجد في هذا الفرع في اليوم المحدد.',
            errors: $errors,
            statusCode: 422
        );
    }

    public static function outsideWorkingHours(array $errors = []): self
    {
        return new self(
            message: 'الوقت المختار خارج ساعات دوام الطبيب.',
            errors: $errors,
            statusCode: 422
        );
    }

    public static function invalidTimeSlot(array $errors = []): self
    {
        return new self(
            message: 'يجب أن يبدأ الموعد على رأس الساعة أو عند الدقيقة 30.',
            errors: $errors,
            statusCode: 422
        );
    }

    public static function invalidStatusTransition(array $errors = []): self
    {
        return new self(
            message: 'لا يمكن إلغاء هذا الموعد في حالته الحالية.',
            errors: $errors,
            statusCode: 422
        );
    }
    public static function appointmentInPast(array $errors = []): self
    {
        return new self(
            message: 'لا يمكن حجز موعد في وقت مضى.',
            errors: $errors,
            statusCode: 422
        );
    }

    public static function slotAlreadyBooked(array $errors = []): self
    {
        return new self(
            message: 'هذا الموعد محجوز مسبقاً، يرجى اختيار وقت آخر.',
            errors: $errors,
            statusCode: 409
        );
    }
}
