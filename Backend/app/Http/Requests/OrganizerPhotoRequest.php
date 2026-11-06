<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OrganizerPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'caption' => ['nullable', 'string', 'max:190'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $event = $this->route('event');
                if ($event && ! ($event->ends_at ?? $event->starts_at)->isPast()) {
                    $validator->errors()->add('event', 'Photos can only be added to past events.');
                }
            },
        ];
    }
}
