<?php

namespace App\Contracts;

use App\DataTransferObjects\EntryData;
use App\Models\Source;
use Illuminate\Support\Collection;

interface SourceAdapterInterface
{
    /**
     * Fetch and normalize entries from the given source.
     *
     * @return Collection<int, EntryData>
     */
    public function fetch(Source $source): Collection;
}