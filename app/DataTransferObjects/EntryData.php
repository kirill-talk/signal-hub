<?php

namespace App\DataTransferObjects;

use Carbon\CarbonInterface;

readonly class EntryData
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $externalId,
        public string $title,
        public string $url,
        public ?string $summary = null,
        public ?string $author = null,
        public ?CarbonInterface $publishedAt = null,
        public array $rawPayload = [],
    ) {
    }
}