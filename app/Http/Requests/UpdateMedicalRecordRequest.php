<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicalRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $record = $this->route('record');
        return $this->user()->can('manageInBranch', $record);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'diagnosis'    => ['required_without:prescription', 'string', 'min:3', 'max:2000'],
            'prescription' => ['sometimes', 'required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
