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
use SimpleXMLElement;
use Throwable;

class RssAtomAdapter implements SourceAdapterInterface
{
    public function __construct(
        protected HtmlSanitizer $sanitizer,
    ) {
    }

    public function fetch(Source $source): Collection
    {
        $headers = array_filter([
            'User-Agent' => 'SignalToNoiseHub/1.0 (+https://signalhub.local)',
            'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml, */*',
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

        $xml = $this->parseXml($response->body());

        if (isset($xml->channel->item)) {
            return $this->parseRssItems($xml->channel->item);
        }

        if (isset($xml->entry)) {
            return $this->parseAtomEntries($xml->entry);
        }

        $atomChildren = $xml->children('http://www.w3.org/2005/Atom');
        if (isset($atomChildren->entry)) {
            return $this->parseAtomEntries($atomChildren->entry);
        }

        return collect();
    }

    protected function parseXml(string $body): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);

        try {
            return new SimpleXMLElement($body, LIBXML_NOCDATA);
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to parse XML feed: ' . $e->getMessage(), 0, $e);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return Collection<int, EntryData>
     */
    protected function parseRssItems(SimpleXMLElement $items): Collection
    {
        $entries = collect();

        foreach ($items as $item) {
            $dcNs = $item->children('http://purl.org/dc/elements/1.1/');
            $dcPrefix = $item->children('dc', true);
            $contentNs = $item->children('http://purl.org/rss/1.0/modules/content/');
            $contentPrefix = $item->children('content', true);

            $rawTitle = trim((string) $item->title);
            $title = $this->sanitizer->plainText($rawTitle);

            $link = trim((string) $item->link);
            if ($link === '') {
                $link = $this->extractRssAtomLink($item);
            }

            $guid = trim((string) $item->guid);

            $contentEncoded = trim((string) ($contentNs->encoded ?? $contentPrefix->encoded ?? ''));
            $description = trim((string) $item->description);
            $rawSummary = $contentEncoded !== '' ? $contentEncoded : $description;
            $summary = $this->sanitizer->sanitize($rawSummary);

            $rawAuthor = trim((string) ($item->author ?: ($dcNs->creator ?? $dcPrefix->creator ?? '')));
            $author = $this->sanitizer->plainText($rawAuthor);

            $pubDate = trim((string) ($item->pubDate ?: ($dcNs->date ?? $dcPrefix->date ?? '')));

            if ($title === '' && $link === '') {
                continue;
            }

            $entries->push(new EntryData(
                externalId: $guid !== '' ? $guid : ($link !== '' ? $link : $title),
                title: $title !== '' ? $title : $link,
                url: $link,
                summary: $summary,
                author: $author !== '' ? $author : null,
                publishedAt: $this->parseDate($pubDate),
                rawPayload: [
                    'format' => 'rss',
                    'guid' => $guid,
                ],
            ));
        }

        return $entries;
    }

    /**
     * @return Collection<int, EntryData>
     */
    protected function parseAtomEntries(SimpleXMLElement $atomEntries): Collection
    {
        $entries = collect();

        foreach ($atomEntries as $entry) {
            $atomNs = $entry->children('http://www.w3.org/2005/Atom');

            $rawTitle = trim((string) ($entry->title ?: ($atomNs->title ?? '')));
            $title = $this->sanitizer->plainText($rawTitle);

            $id = trim((string) ($entry->id ?: ($atomNs->id ?? '')));
            $link = $this->extractAtomLink($entry);

            $rawSummary = trim((string) ($entry->content ?: $entry->summary ?: ($atomNs->content ?? $atomNs->summary ?? '')));
            $summary = $this->sanitizer->sanitize($rawSummary);

            $rawAuthor = trim((string) ($entry->author->name ?? $atomNs->author->name ?? ''));
            $author = $this->sanitizer->plainText($rawAuthor);

            $date = trim((string) ($entry->published ?: $entry->updated ?: ($atomNs->published ?? $atomNs->updated ?? '')));

            if ($title === '' && $link === '') {
                continue;
            }

            $entries->push(new EntryData(
                externalId: $id !== '' ? $id : ($link !== '' ? $link : $title),
                title: $title !== '' ? $title : $link,
                url: $link,
                summary: $summary,
                author: $author !== '' ? $author : null,
                publishedAt: $this->parseDate($date),
                rawPayload: [
                    'format' => 'atom',
                    'id' => $id,
                ],
            ));
        }

        return $entries;
    }

    protected function extractRssAtomLink(SimpleXMLElement $item): string
    {
        $atomNs = $item->children('http://www.w3.org/2005/Atom');
        if (isset($atomNs->link)) {
            $link = $this->pickLinkHref($atomNs->link);
            if ($link !== '') {
                return $link;
            }
        }

        $atomPrefix = $item->children('atom', true);
        if (isset($atomPrefix->link)) {
            return $this->pickLinkHref($atomPrefix->link);
        }

        return '';
    }

    protected function extractAtomLink(SimpleXMLElement $entry): string
    {
        if (isset($entry->link)) {
            $link = $this->pickLinkHref($entry->link);
            if ($link !== '') {
                return $link;
            }
        }

        $atomNs = $entry->children('http://www.w3.org/2005/Atom');
        if (isset($atomNs->link)) {
            $link = $this->pickLinkHref($atomNs->link);
            if ($link !== '') {
                return $link;
            }
        }

        return trim((string) $entry->link);
    }

    protected function pickLinkHref(SimpleXMLElement $links): string
    {
        foreach ($links as $linkNode) {
            $attributes = $linkNode->attributes();
            $rel = isset($attributes['rel']) ? (string) $attributes['rel'] : 'alternate';
            $href = isset($attributes['href']) ? trim((string) $attributes['href']) : '';

            if ($rel === 'alternate' && $href !== '') {
                return $href;
            }
        }

        if (isset($links[0])) {
            $attributes = $links[0]->attributes();
            if (isset($attributes['href'])) {
                return trim((string) $attributes['href']);
            }
        }

        return '';
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
