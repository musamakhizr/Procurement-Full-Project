<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'image_url' => ['nullable', 'url'],
            'moq' => ['required', 'integer', 'min:1'],
            'lead_time_min_days' => ['required', 'integer', 'min:1'],
            'lead_time_max_days' => ['required', 'integer', 'gte:lead_time_min_days'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'is_verified' => ['boolean'],
            'is_customizable' => ['boolean'],
            'is_active' => ['boolean'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'price_tiers' => ['required', 'array', 'min:1'],
            'price_tiers.*.min_quantity' => ['required', 'integer', 'min:1'],
            'price_tiers.*.max_quantity' => ['nullable', 'integer', 'min:1'],
            'price_tiers.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
