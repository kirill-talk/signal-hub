<?php

namespace App\Services;

use App\DataTransferObjects\EntryData;
use App\Models\Entry;
use App\Models\Source;
use App\Services\Adapters\AdapterFactory;

class EntryIngestionService
{
    public function __construct(
        protected AdapterFactory $adapterFactory,
        protected HtmlSanitizer $sanitizer,
        protected NoiseFilterService $noiseFilter,
    ) {
    }

    /**
     * Fetch entries from a source and persist only new ones.
     *
     * @return int Number of newly created entries
     */
    public function ingest(Source $source): int
    {
        $adapter = $this->adapterFactory->make($source->adapter_type);
        $entries = $adapter->fetch($source);

        $createdCount = 0;

        foreach ($entries as $entryData) {
            $hash = $this->computeHash($source, $entryData);

            $exists = Entry::query()
                ->where('user_id', $source->user_id)
                ->where('external_id_hash', $hash)
                ->exists();

            if ($exists) {
                continue;
            }

            $cleanSummary = $this->sanitizer->sanitize($entryData->summary);
            $evaluation = $this->noiseFilter->evaluate(
                userId: $source->user_id,
                sourceId: $source->id,
                topicId: $source->topic_id,
                title: $entryData->title,
                summary: $cleanSummary,
            );

            Entry::create([
                'user_id' => $source->user_id,
                'source_id' => $source->id,
                'topic_id' => $source->topic_id,
                'external_id_hash' => $hash,
                'title' => $entryData->title,
                'url' => $entryData->url,
                'author' => $entryData->author,
                'summary' => $cleanSummary,
                'published_at' => $entryData->publishedAt ?? now(),
                'is_read' => false,
                'is_bookmarked' => false,
                'is_noise' => $evaluation['is_noise'],
                'is_highlighted' => $evaluation['is_highlighted'],
                'raw_payload' => $entryData->rawPayload,
            ]);

            $createdCount++;
        }

        $source->update([
            'last_fetched_at' => now(),
            'status' => 'active',
            'error_count' => 0,
            'last_error' => null,
        ]);

        return $createdCount;
    }

    public function computeHash(Source $source, EntryData $entryData): string
    {
        return hash('sha256', $source->id . ':' . $entryData->externalId);
    }
}