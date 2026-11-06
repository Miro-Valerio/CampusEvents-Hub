<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrganizerAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:10000'],
            'course' => ['nullable', 'string', 'max:100'],
            'pinned' => ['sometimes', 'boolean'],
        ];
    }
}
