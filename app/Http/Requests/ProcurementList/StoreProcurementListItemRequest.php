<?php

namespace App\Http\Requests\ProcurementList;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcurementListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
