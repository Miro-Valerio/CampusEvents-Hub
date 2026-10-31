<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OrganizerEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'],
            'description' => ['required', 'string', 'max:10000'],
            'poster_url' => ['nullable', 'url', 'max:2048'],
            'venue' => ['required', 'string', 'max:190'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'category' => ['required', 'string', 'max:100'],
            'rules' => ['nullable', 'string', 'max:10000'],
            'capacity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $event = $this->route('event');
                if (! $event || $validator->errors()->has('capacity')) {
                    return;
                }

                $going = $event->rsvps()->where('status', 'going')->count();
                $occupiedSeats = (int) $event->baseline_registered_count + $going;
                if ((int) $this->input('capacity') < $occupiedSeats) {
                    $validator->errors()->add('capacity', "Capacity cannot be lower than the {$occupiedSeats} occupied seats, including existing registrations.");
                }
            },
        ];
    }
}
