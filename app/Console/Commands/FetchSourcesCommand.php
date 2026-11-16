<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\EntryIngestionService;
use Illuminate\Console\Command;
use Throwable;

class FetchSourcesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sources:fetch {source_id? : Optional ID of a specific source to fetch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and ingest new entries from configured sources';

    /**
     * Execute the console command.
     */
    public function handle(EntryIngestionService $ingestionService): int
    {
        $sourceId = $this->argument('source_id');

        $sources = Source::query()
            ->when($sourceId, fn($query) => $query->where('id', $sourceId))
            ->get();

        if ($sources->isEmpty()) {
            $this->warn('No matching sources found.');

            return self::SUCCESS;
        }

        foreach ($sources as $source) {
            $this->line("Fetching [{$source->name}] ({$source->url})...");

            try {
                $createdCount = $ingestionService->ingest($source);
                $this->info("  -> Done! {$createdCount} new entries saved.");
            } catch (Throwable $e) {
                $source->update([
                    'status' => 'failing',
                    'error_count' => $source->error_count + 1,
                    'last_error' => $e->getMessage(),
                ]);

                $this->error("  -> Failed: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}