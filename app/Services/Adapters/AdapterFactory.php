<?php

namespace App\Services\Adapters;

use App\Contracts\SourceAdapterInterface;
use InvalidArgumentException;

class AdapterFactory
{
    public function make(string $adapterType): SourceAdapterInterface
    {
        return match ($adapterType) {
            'rss', 'github' => app(RssAtomAdapter::class),
            'json' => app(JsonFeedAdapter::class),
            default => throw new InvalidArgumentException("Unsupported adapter type: {$adapterType}"),
        };
    }
}