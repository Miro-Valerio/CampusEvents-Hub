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
            'role' => ['sometimes', 'string', Rule::in(['student', 'organizer'])],
            'organization_name' => ['required_if:role,organizer', 'nullable', 'string', 'max:190'],
            'description' => ['required_if:role,organizer', 'nullable', 'string', 'max:5000'],
        ];
    }
}
