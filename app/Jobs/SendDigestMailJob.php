<?php

namespace App\Jobs;

use App\Mail\DailyDigestMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendDigestMailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user,
    ) {
    }

    public function handle(): void
    {
        $since = $this->user->last_digest_sent_at
            ?? ($this->user->digest_frequency === 'weekly' ? now()->subWeek() : now()->subDay());

        $entries = $this->user->entries()
            ->where('is_noise', false)
            ->where('is_read', false)
            ->where('created_at', '>=', $since)
            ->with(['source', 'topic'])
            ->orderByDesc('is_highlighted')
            ->orderByDesc('published_at')
            ->limit(40)
            ->get();

        if ($entries->isEmpty()) {
            return;
        }

        $grouped = $entries->groupBy(
            fn($entry): string => $entry->topic?->name ?? 'General'
        );

        Mail::to($this->user->email)->send(
            new DailyDigestMail($this->user, $grouped, $entries->count())
        );

        $this->user->update([
            'last_digest_sent_at' => now(),
        ]);
    }
}