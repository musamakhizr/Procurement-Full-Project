<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'description',
        'image_url',
        'source_platform',
        'source_product_id',
        'source_url',
        'source_image_url',
        'source_category_label',
        'cat_from_api',
        'import_status',
        'import_error',
        'import_total_tasks',
        'import_completed_tasks',
        'source_payload',
        'import_api_debug',
        'moq',
        'lead_time_min_days',
        'lead_time_max_days',
        'stock_quantity',
        'is_verified',
        'is_customizable',
        'is_active',
        'base_price',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_customizable' => 'boolean',
        'is_active' => 'boolean',
        'base_price' => 'decimal:2',
        'cat_from_api' => 'array',
        'source_payload' => 'array',
        'import_api_debug' => 'array',
        'import_total_tasks' => 'integer',
        'import_completed_tasks' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class)->orderBy('min_quantity');
    }

    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    protected function formattedLeadTime(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->lead_time_min_days}-{$this->lead_time_max_days} days",
        );
    }

    protected function priceRange(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->priceTiers->isNotEmpty()
                ? number_format((float) $this->priceTiers->min('price'), 2).' - '.number_format((float) $this->priceTiers->max('price'), 2)
                : number_format((float) $this->base_price, 2),
        );
    }

    public function priceForQuantity(int $quantity): float
    {
        $tier = $this->priceTiers
            ->first(fn (ProductPriceTier $tier) => $quantity >= $tier->min_quantity && ($tier->max_quantity === null || $quantity <= $tier->max_quantity));

        return (float) ($tier?->price ?? $this->base_price);
    }

    /**
     * @return Collection<int, string>
     */
    public function galleryPaths(): Collection
    {
        $variantSourceImages = $this->variantSourceImageUrls();
        $galleryPayloadImages = collect(data_get($this->source_payload, 'images', []))
            ->slice(0, 4)
            ->all();

        $sourceGalleryImages = collect([
            data_get($this->source_payload, 'main_image_url'),
            data_get($this->source_payload, 'image_url'),
        ])
            ->merge($galleryPayloadImages)
            ->filter(fn ($path) => is_string($path) && $path !== '' && ! $this->shouldIgnoreImageUrl($path))
            ->reject(fn (string $path) => $variantSourceImages->contains($path))
            ->unique()
            ->values();

        if ($this->shouldPreferStoredMedia()) {
            return $this->storedImagePathsForSection('gallery', $this->image_url, true);
        }

        return $this->mergedImagePathsForSection('gallery', $sourceGalleryImages, $this->image_url);
    }

    /**
     * @return Collection<int, string>
     */
    public function descriptionImagePaths(): Collection
    {
        $sourceDescriptionImages = collect(data_get($this->source_payload, 'description_images', []))
            ->filter(fn ($path) => is_string($path) && $path !== '' && ! $this->shouldIgnoreImageUrl($path))
            ->values();

        if ($this->shouldPreferStoredMedia()) {
            return $this->storedImagePathsForSection('description');
        }

        return $this->mergedImagePathsForSection('description', $sourceDescriptionImages);
    }

    private function shouldPreferStoredMedia(): bool
    {
        return in_array($this->import_status, ['completed', 'failed'], true);
    }

    /**
     * @param  Collection<int, string>  $sourcePaths
     */
    private function mergedImagePathsForSection(string $section, Collection $sourcePaths, ?string $fallbackPrimaryPath = null): Collection
    {
        $basePaths = $sourcePaths->values()->all();

        if ($fallbackPrimaryPath !== null && $section === 'gallery' && $basePaths === []) {
            $basePaths[] = $fallbackPrimaryPath;
        }

        if ($this->relationLoaded('productImages')) {
            $variantSourceImages = $section === 'gallery' ? $this->variantSourceImageUrls() : collect();
            $variantStoredImages = $section === 'gallery' ? $this->variantStoredImagePaths() : collect();
            $storedImages = $this->productImages
                ->where('section', $section)
                ->sortBy('sort_order')
                ->values();

            foreach ($storedImages as $image) {
                $path = $image->path;
                $sourceUrl = $image->source_url;

                if (! is_string($path) || $path === '') {
                    continue;
                }

                if ($this->shouldIgnoreImageUrl($sourceUrl) || $this->shouldIgnoreImageUrl($path)) {
                    continue;
                }

                if (
                    $section === 'gallery'
                    && (
                        (is_string($sourceUrl) && $sourceUrl !== '' && $variantSourceImages->contains($sourceUrl))
                        || $variantStoredImages->contains($path)
                    )
                ) {
                    continue;
                }

                $sortOrder = max((int) $image->sort_order, 0);
                $basePaths[$sortOrder] = $path;
            }
        }

        return collect($basePaths)
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function storedImagePathsForSection(string $section, ?string $fallbackPrimaryPath = null, bool $excludeVariantImages = false): Collection
    {
        $variantSourceImages = $excludeVariantImages ? $this->variantSourceImageUrls() : collect();
        $variantStoredImages = $excludeVariantImages ? $this->variantStoredImagePaths() : collect();

        $storedImages = $this->relationLoaded('productImages')
            ? $this->productImages->where('section', $section)->sortBy('sort_order')->values()
            : $this->productImages()->where('section', $section)->orderBy('sort_order')->get();

        $paths = collect($storedImages)
            ->map(function ($image) use ($section, $variantSourceImages, $variantStoredImages) {
                $path = $image->path;
                $sourceUrl = $image->source_url;

                if (! is_string($path) || $path === '') {
                    return null;
                }

                if ($this->shouldIgnoreImageUrl($sourceUrl) || $this->shouldIgnoreImageUrl($path)) {
                    return null;
                }

                if (
                    $section === 'gallery'
                    && (
                        (is_string($sourceUrl) && $sourceUrl !== '' && $variantSourceImages->contains($sourceUrl))
                        || $variantStoredImages->contains($path)
                    )
                ) {
                    return null;
                }

                return $path;
            })
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->unique()
            ->values();

        if (
            $paths->isEmpty()
            && $section === 'gallery'
            && is_string($fallbackPrimaryPath)
            && $fallbackPrimaryPath !== ''
            && ! $this->shouldIgnoreImageUrl($fallbackPrimaryPath)
        ) {
            return collect([$fallbackPrimaryPath]);
        }

        return $paths;
    }

    /**
     * @return Collection<int, string>
     */
    private function variantSourceImageUrls(): Collection
    {
        if ($this->relationLoaded('variants')) {
            return $this->variants
                ->pluck('source_image_url')
                ->filter(fn ($path) => is_string($path) && $path !== '')
                ->values();
        }

        return collect(data_get($this->source_payload, 'variants', []))
            ->map(fn ($variant) => is_array($variant) ? ($variant['image_url'] ?? null) : null)
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function variantStoredImagePaths(): Collection
    {
        if (! $this->relationLoaded('variants')) {
            return collect();
        }

        return $this->variants
            ->pluck('image_url')
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values();
    }

    private function shouldIgnoreImageUrl(?string $url): bool
    {
        if (! is_string($url) || trim($url) === '') {
            return false;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        if ($host === 'www.o0b.cn' && $path === '/i.php') {
            return true;
        }

        return Str::endsWith($path, '/spaceball.gif');
    }
}
