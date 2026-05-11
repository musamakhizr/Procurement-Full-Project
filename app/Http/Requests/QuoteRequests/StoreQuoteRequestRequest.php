<?php

namespace App\Http\Requests\QuoteRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'distinct', 'exists:procurement_list_items,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
