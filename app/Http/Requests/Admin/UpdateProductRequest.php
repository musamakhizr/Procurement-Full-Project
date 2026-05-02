<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')->id;

        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'sku' => ['sometimes', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'image_url' => ['nullable', 'url'],
            'import_source' => ['sometimes', 'array'],
            'import_source.platform' => ['required_with:import_source', 'string', 'max:50'],
            'import_source.num_iid' => ['required_with:import_source', 'string', 'max:255'],
            'import_source.detail_url' => ['nullable', 'url'],
            'import_source.image_url' => ['nullable', 'url'],
            'import_source.description' => ['nullable', 'string'],
            'import_source.description_html' => ['nullable', 'string'],
            'import_source.images' => ['nullable', 'array'],
            'import_source.images.*' => ['url'],
            'moq' => ['sometimes', 'integer', 'min:1'],
            'lead_time_min_days' => ['sometimes', 'integer', 'min:1'],
            'lead_time_max_days' => ['sometimes', 'integer', 'gte:lead_time_min_days'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'is_verified' => ['sometimes', 'boolean'],
            'is_customizable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'base_price' => ['sometimes', 'numeric', 'min:0'],
            'price_tiers' => ['sometimes', 'array', 'min:1'],
            'price_tiers.*.min_quantity' => ['required_with:price_tiers', 'integer', 'min:1'],
            'price_tiers.*.max_quantity' => ['nullable', 'integer', 'min:1'],
            'price_tiers.*.price' => ['required_with:price_tiers', 'numeric', 'min:0'],
        ];
    }
}
