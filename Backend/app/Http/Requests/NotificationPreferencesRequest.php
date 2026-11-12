<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'announcements' => ['sometimes', 'boolean'],
            'event_updates' => ['sometimes', 'boolean'],
            'event_reminders' => ['sometimes', 'boolean'],
        ];
    }
}
