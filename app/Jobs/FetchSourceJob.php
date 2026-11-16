<?php

namespace App\Jobs;

use App\Models\Source;
use App\Services\EntryIngestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class FetchSourceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 15;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public Source $source,
    ) {
    }

    public function handle(EntryIngestionService $ingestionService): void
    {
        $host = parse_url($this->source->url, PHP_URL_HOST) ?: 'unknown';
        $rateLimitKey = 'fetch-domain:' . $host;

        $executed = RateLimiter::attempt(
            key: $rateLimitKey,
            maxAttempts: 10,
            callback: function () use ($ingestionService): void {
                try {
                    $ingestionService->ingest($this->source);
                } catch (Throwable $e) {
                    $this->source->update([
                        'status' => 'failing',
                        'error_count' => $this->source->error_count + 1,
                        'last_error' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            },
            decaySeconds: 60,
        );

        if (!$executed) {
            $this->release(15);
        }
    }
}