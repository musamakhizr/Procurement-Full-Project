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
            'import_source.title' => ['nullable', 'string'],
            'import_source.platform' => ['required_with:import_source', 'string', 'max:50'],
            'import_source.num_iid' => ['required_with:import_source', 'string', 'max:255'],
            'import_source.detail_url' => ['nullable', 'url'],
            'import_source.image_url' => ['nullable', 'url'],
            'import_source.main_image_url' => ['nullable', 'url'],
            'import_source.classified_category' => ['nullable', 'string', 'max:255'],
            'import_source.description' => ['nullable', 'string'],
            'import_source.description_html' => ['nullable', 'string'],
            'import_source.images' => ['nullable', 'array'],
            'import_source.images.*' => ['url'],
            'import_source.description_images' => ['nullable', 'array'],
            'import_source.description_images.*' => ['url'],
            'import_source.variants' => ['nullable', 'array'],
            'import_source.variants.*.sku_id' => ['required_with:import_source.variants', 'string', 'max:255'],
            'import_source.variants.*.properties_key' => ['nullable', 'string', 'max:255'],
            'import_source.variants.*.properties_name' => ['nullable', 'string'],
            'import_source.variants.*.label' => ['nullable', 'string', 'max:255'],
            'import_source.variants.*.image_url' => ['nullable', 'url'],
            'import_source.variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'import_source.variants.*.original_price' => ['nullable', 'numeric', 'min:0'],
            'import_source.variants.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'import_source.variants.*.option_values' => ['nullable', 'array'],
            'import_source.variants.*.option_values.*.key' => ['required_with:import_source.variants.*.option_values', 'string', 'max:255'],
            'import_source.variants.*.option_values.*.group_name' => ['required_with:import_source.variants.*.option_values', 'string', 'max:255'],
            'import_source.variants.*.option_values.*.value' => ['required_with:import_source.variants.*.option_values', 'string', 'max:255'],
            'import_source.processed_main_image' => ['nullable', 'array'],
            'import_source.processed_main_image.mime_type' => ['required_with:import_source.processed_main_image', 'string', 'max:100'],
            'import_source.processed_main_image.data' => ['required_with:import_source.processed_main_image', 'string'],
            'import_source.processed_main_image.source_url' => ['nullable', 'url'],
            'import_source.processed_gallery_images' => ['nullable', 'array'],
            'import_source.processed_gallery_images.*.mime_type' => ['required_with:import_source.processed_gallery_images', 'string', 'max:100'],
            'import_source.processed_gallery_images.*.data' => ['required_with:import_source.processed_gallery_images', 'string'],
            'import_source.processed_gallery_images.*.source_url' => ['nullable', 'url'],
            'import_source.processed_description_images' => ['nullable', 'array'],
            'import_source.processed_description_images.*.mime_type' => ['required_with:import_source.processed_description_images', 'string', 'max:100'],
            'import_source.processed_description_images.*.data' => ['required_with:import_source.processed_description_images', 'string'],
            'import_source.processed_description_images.*.source_url' => ['nullable', 'url'],
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
