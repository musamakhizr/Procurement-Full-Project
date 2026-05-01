<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class RemoteImage
{
    /**
     * Only allow a tightly scoped set of hosts to avoid turning this into a generic proxy.
     *
     * @var list<string>
     */
    private const PROXIED_HOST_SUFFIXES = [
        'alicdn.com',
    ];

    public static function proxiedUrl(?string $url): ?string
    {
        if (! self::shouldProxy($url)) {
            return $url;
        }

        return URL::signedRoute('remote-images.show', [
            'url' => $url,
        ]);
    }

    public static function shouldProxy(?string $url): bool
    {
        if (blank($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = Str::lower((string) parse_url($url, PHP_URL_SCHEME));
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        foreach (self::PROXIED_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || Str::endsWith($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function requestHeaders(string $url): array
    {
        $headers = [
            'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36',
        ];

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        if ($host === 'alicdn.com' || Str::endsWith($host, '.alicdn.com')) {
            $headers['Referer'] = 'https://detail.1688.com/';
        }

        return $headers;
    }
}
