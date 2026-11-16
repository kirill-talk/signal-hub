<?php

use App\Jobs\FetchSourceJob;
use App\Models\Source;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Source::query()
        ->get()
        ->filter(function (Source $source): bool {
            if ($source->last_fetched_at === null) {
                return true;
            }

            return $source->last_fetched_at->addMinutes($source->fetch_interval_minutes)->isPast();
        })
        ->each(function (Source $source): void {
            FetchSourceJob::dispatch($source);
        });
})->name('dispatch-due-sources')->everyMinute()->withoutOverlapping();

Schedule::command('digests:send')->everyFifteenMinutes()->withoutOverlapping();