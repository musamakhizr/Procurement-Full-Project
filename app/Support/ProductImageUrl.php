<?php

namespace App\Support;

use Illuminate\Support\Str;

final class ProductImageUrl
{
    public static function fromStoredPath(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return RemoteImage::proxiedUrl($value);
        }

        return url('/storage/'.ltrim(Str::replace('\\', '/', $value), '/'));
    }
}
