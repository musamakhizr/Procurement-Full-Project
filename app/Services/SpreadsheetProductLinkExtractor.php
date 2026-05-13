<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class SpreadsheetProductLinkExtractor
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

        return $this->extractLinks($contents);
    }

    private function readXlsxContents(UploadedFile $file): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('XLSX import requires the PHP ZipArchive extension. Please enable it or upload CSV/TXT.');
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
    private function extractLinks(string $contents): array
    {
        $decodedContents = html_entity_decode($contents, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        preg_match_all('~https?://[^\s<>"\']+~i', $decodedContents, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $url) => trim($url, " \t\n\r\0\x0B,;()[]{}<>\"'"))
            ->filter(fn (string $url) => $this->isSupportedMarketplaceLink($url))
            ->unique()
            ->values()
            ->all();
    }

    private function isSupportedMarketplaceLink(string $url): bool
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return str_contains($host, '1688.com')
            || str_contains($host, 'taobao.com')
            || str_contains($host, 'tmall.com')
            || str_contains($host, 'jd.com');
    }
}
