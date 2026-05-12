<?php

namespace App\Support;

use Illuminate\Support\Str;

final class ProductImageUrl
{
    public static function fromStoredPath(?string $value, bool $proxyRemote = true): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $proxyRemote ? RemoteImage::proxiedUrl($value) : $value;
        }

        return url('/storage/'.ltrim(Str::replace('\\', '/', $value), '/'));
    }
}
