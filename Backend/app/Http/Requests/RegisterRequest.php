<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    private const DEPARTMENTS = ['CCS', 'BSCRIM', 'COE', 'CED', 'CAD', 'CBA'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['prohibited'],
            'course' => ['nullable', 'string', 'max:100'],
            'department' => ['required', 'string', Rule::in(self::DEPARTMENTS)],
            'organization_name' => ['prohibited'],
            'description' => ['prohibited'],
        ];
    }
}
