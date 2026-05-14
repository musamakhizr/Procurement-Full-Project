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
        $galleryPayloadImages = collect(
            data_get($this->source_payload, 'original_images', data_get($this->source_payload, 'images', []))
        )
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

        if ($this->shouldUseSourceMediaOnly()) {
            return $this->sourceGalleryFallback($sourceGalleryImages);
        }

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
        $sourceDescriptionImages = $this->sourceDescriptionImageUrls(preferOriginal: true);

        if ($this->shouldUseSourceMediaOnly()) {
            return $sourceDescriptionImages;
        }

        if ($this->shouldPreferStoredMedia()) {
            $storedDescriptionImages = $this->storedImagePathsForSection('description');

            if ($storedDescriptionImages->isNotEmpty()) {
                return $storedDescriptionImages;
            }

            if ($this->allDescriptionImagesRejected()) {
                return collect();
            }

            return $sourceDescriptionImages;
        }

        return $this->mergedImagePathsForSection('description', $sourceDescriptionImages);
    }

   private function shouldPreferStoredMedia(): bool
{
    return $this->import_status === 'completed';
}

private function shouldUseSourceMediaOnly(): bool
{
    return in_array($this->import_status, ['pending', 'processing', 'failed'], true);
}

    /**
     * @param  Collection<int, string>  $sourceGalleryImages
     * @return Collection<int, string>
     */
    private function sourceGalleryFallback(Collection $sourceGalleryImages): Collection
    {
        if ($sourceGalleryImages->isNotEmpty()) {
            return $sourceGalleryImages;
        }

        return collect([$this->image_url, $this->source_image_url])
            ->filter(fn ($path) => is_string($path) && $path !== '' && ! $this->shouldIgnoreImageUrl($path))
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function sourceDescriptionImageUrls(bool $preferOriginal = false): Collection
    {
        $primaryImages = $preferOriginal
            ? data_get($this->source_payload, 'original_description_images', [])
            : data_get($this->source_payload, 'description_images', []);

        $fallbackImages = $preferOriginal
            ? data_get($this->source_payload, 'description_images', [])
            : data_get($this->source_payload, 'original_description_images', []);

        $payloadImages = collect($primaryImages)
            ->merge(is_array($fallbackImages) ? $fallbackImages : [])
            ->filter(fn ($path) => is_string($path) && $path !== '' && ! $this->shouldIgnoreImageUrl($path))
            ->unique()
            ->values();

        if ($payloadImages->isNotEmpty()) {
            return $payloadImages;
        }

        $descriptionHtml = data_get($this->source_payload, 'description_html');

        if (! is_string($descriptionHtml) || trim($descriptionHtml) === '') {
            return collect();
        }

        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', html_entity_decode($descriptionHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $matches);

        return collect($matches[1] ?? [])
            ->filter(fn ($path) => is_string($path) && $path !== '' && ! $this->shouldIgnoreImageUrl($path))
            ->map(fn (string $path) => Str::startsWith($path, '//') ? 'https:'.$path : $path)
            ->filter(fn (string $path) => filter_var($path, FILTER_VALIDATE_URL))
            ->unique()
            ->values();
    }

    private function allDescriptionImagesRejected(): bool
    {
        $results = collect(data_get($this->import_api_debug, 'classify_description_results', []))
            ->filter(fn ($row) => is_array($row) && is_string($row['status'] ?? null))
            ->values();

        return $results->isNotEmpty()
            && $results->every(fn (array $row) => ($row['status'] ?? null) === 'rejected');
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
