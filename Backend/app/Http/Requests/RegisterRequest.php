<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
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
            'department' => ['required', 'string', Rule::exists('departments', 'code')
                ->where('is_active', true)
                ->where('is_legacy', false)],
            'organization_name' => ['prohibited'],
            'description' => ['prohibited'],
        ];
    }
}
