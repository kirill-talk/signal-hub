<?php

namespace App\Services\Adapters;

use App\Contracts\SourceAdapterInterface;
use App\DataTransferObjects\EntryData;
use App\Models\Source;
use App\Services\HtmlSanitizer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class JsonFeedAdapter implements SourceAdapterInterface
{
    public function __construct(
        protected HtmlSanitizer $sanitizer,
    ) {
    }

    public function fetch(Source $source): Collection
    {
        $headers = array_filter([
            'User-Agent' => 'SignalToNoiseHub/1.0 (+https://signalhub.local)',
            'Accept' => 'application/feed+json, application/json, */*',
            'If-None-Match' => $source->etag,
            'If-Modified-Since' => $source->last_modified,
        ]);

        $response = Http::timeout(15)
            ->withHeaders($headers)
            ->get($source->url);

        if ($response->status() === 304) {
            return collect();
        }

        $response->throw();

        if ($etag = $response->header('ETag')) {
            $source->etag = $etag;
        }

        if ($lastModified = $response->header('Last-Modified')) {
            $source->last_modified = $lastModified;
        }

        $payload = $response->json();

        if (!is_array($payload) || !isset($payload['items']) || !is_array($payload['items'])) {
            throw new RuntimeException('Invalid JSON Feed payload: missing items array.');
        }

        $entries = collect();

        foreach ($payload['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));
            $url = trim((string) ($item['url'] ?? $item['external_url'] ?? ''));
            $title = $this->sanitizer->plainText((string) ($item['title'] ?? ''));
            $rawSummary = trim((string) ($item['content_html'] ?? $item['content_text'] ?? $item['summary'] ?? ''));
            $summary = $this->sanitizer->sanitize($rawSummary);
            $author = $this->extractAuthor($item);
            $date = trim((string) ($item['date_published'] ?? $item['date_modified'] ?? ''));

            if ($title === '' && $url === '' && $summary === null) {
                continue;
            }

            $entries->push(new EntryData(
                externalId: $id !== '' ? $id : ($url !== '' ? $url : $title),
                title: $title !== '' ? $title : ($url !== '' ? $url : 'Untitled'),
                url: $url,
                summary: $summary,
                author: $author,
                publishedAt: $this->parseDate($date),
                rawPayload: [
                    'format' => 'json',
                    'id' => $id,
                ],
            ));
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function extractAuthor(array $item): ?string
    {
        if (isset($item['author']['name']) && is_string($item['author']['name'])) {
            $name = $this->sanitizer->plainText($item['author']['name']);

            return $name !== '' ? $name : null;
        }

        if (isset($item['authors'][0]['name']) && is_string($item['authors'][0]['name'])) {
            $name = $this->sanitizer->plainText($item['authors'][0]['name']);

            return $name !== '' ? $name : null;
        }

        return null;
    }

    protected function parseDate(string $dateString): ?CarbonInterface
    {
        if ($dateString === '') {
            return null;
        }

        try {
            return Carbon::parse($dateString);
        } catch (Throwable) {
            return null;
        }
    }
}
