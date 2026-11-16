<?php

namespace App\Console\Commands;

use App\Jobs\SendDigestMailJob;
use App\Models\User;
use DateTimeZone;
use Illuminate\Console\Command;

class SendScheduledDigests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'digests:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch scheduled email digests matching each user timezone';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::query()
            ->whereIn('digest_frequency', ['daily', 'weekly'])
            ->get();

        $dispatched = 0;

        foreach ($users as $user) {
            if (!$this->isDueForUser($user)) {
                continue;
            }

            SendDigestMailJob::dispatch($user);
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} digest jobs.");

        return self::SUCCESS;
    }

    protected function isDueForUser(User $user): bool
    {
        $timezone = in_array($user->timezone, DateTimeZone::listIdentifiers(), true)
            ? $user->timezone
            : 'UTC';

        $localNow = now()->setTimezone($timezone);

        [$hour, $minute] = array_pad(explode(':', (string) $user->digest_time), 2, '0');
        $scheduledLocal = $localNow->copy()->setTime((int) $hour, (int) $minute, 0);

        if ($localNow->lt($scheduledLocal) || $localNow->gte($scheduledLocal->copy()->addMinutes(15))) {
            return false;
        }

        if ($user->last_digest_sent_at === null) {
            return true;
        }

        $lastSentLocal = $user->last_digest_sent_at->copy()->setTimezone($timezone);

        if ($user->digest_frequency === 'daily') {
            return !$lastSentLocal->isSameDay($localNow);
        }

        if ($user->digest_frequency === 'weekly') {
            return $lastSentLocal->lte($localNow->copy()->subDays(6));
        }

        return false;
    }
}