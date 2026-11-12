<?php

namespace App\Http\Requests;

use App\Models\EventReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(EventReport::REASONS)],
            'details' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
