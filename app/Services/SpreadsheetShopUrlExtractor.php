<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class SpreadsheetShopUrlExtractor
{
    /**
     * @return array<int,string>
     */
    public function extractFromUploadedFile(UploadedFile $file): array
    {
        $extension = Str::lower((string) $file->getClientOriginalExtension());

        $contents = $extension === 'xlsx'
            ? $this->readXlsxContents($file)
            : (string) file_get_contents($file->getRealPath());

        return $this->extractShopSeedUrls($contents);
    }

    private function readXlsxContents(UploadedFile $file): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('XLSX shop import requires the PHP ZipArchive extension. Please enable it or upload CSV/TXT.');
        }

        $zip = new \ZipArchive();

        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException('Unable to read the uploaded XLSX file.');
        }

        $contents = '';

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = (string) $zip->getNameIndex($index);
            $lowerEntryName = Str::lower($entryName);

            if (! Str::endsWith($lowerEntryName, ['.xml', '.rels', '.txt'])) {
                continue;
            }

            $entryContents = $zip->getFromIndex($index);

            if (is_string($entryContents) && $entryContents !== '') {
                $contents .= "\n".$entryContents;
            }
        }

        $zip->close();

        return $contents;
    }

    /**
     * @return array<int,string>
     */
    private function extractShopSeedUrls(string $contents): array
    {
        $decodedContents = html_entity_decode($contents, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        preg_match_all('~https?://[^\s<>"\']+~i', $decodedContents, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $url) => trim($url, " \t\n\r\0\x0B,;()[]{}<>\"'"))
            ->filter(fn (string $url) => $this->isSupportedShopSeedUrl($url))
            ->unique(fn (string $url) => $this->productIdentity($url))
            ->values()
            ->all();
    }

    private function isSupportedShopSeedUrl(string $url): bool
    {
        return $this->detectedProductId($url) !== null;
    }

    private function productIdentity(string $url): string
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $numIid = $this->detectedProductId($url);

        if ($numIid !== null) {
            return $this->platformKey($host).':'.$numIid;
        }

        return $url;
    }

    private function detectedProductId(string $url): ?string
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (str_contains($host, '1688.com')) {
            preg_match('/offer\/(\d+)\.html/i', $path, $matches);
            $numIid = $matches[1] ?? $query['offerId'] ?? $query['offer_id'] ?? $query['id'] ?? null;

            return $this->validNumericId($numIid);
        }

        if (str_contains($host, 'jd.com')) {
            preg_match('/\/(\d+)\.html/i', $path, $matches);
            $numIid = $matches[1] ?? $query['id'] ?? null;

            return $this->validNumericId($numIid);
        }

        if (str_contains($host, 'taobao.com') || str_contains($host, 'tmall.com')) {
            return $this->validNumericId($query['id'] ?? null);
        }

        return null;
    }

    private function validNumericId(mixed $value): ?string
    {
        return is_scalar($value) && preg_match('/^\d+$/', (string) $value) === 1
            ? (string) $value
            : null;
    }

    private function platformKey(string $host): string
    {
        if (str_contains($host, '1688.com')) {
            return '1688';
        }

        if (str_contains($host, 'jd.com')) {
            return 'jd';
        }

        return 'taobao';
    }
}
