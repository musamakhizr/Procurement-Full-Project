<?php

namespace App\Http\Requests\Sourcing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSourcingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['custom', 'links'])],
            'title' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
            'budget_text' => ['nullable', 'string', 'max:255'],
            'delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'links' => ['array'],
            'links.*' => ['url'],
        ];
    }
}
