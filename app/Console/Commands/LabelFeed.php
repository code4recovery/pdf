<?php

namespace App\Console\Commands;

use App\Models\FeedLabel;
use App\Models\UsageEvent;
use App\Models\UsageMonthly;
use App\Support\FeedIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class LabelFeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:label
        {feed : A feed URL, a full 64-character fingerprint, or a hex prefix (8+ chars) as shown on the usage dashboard}
        {label : The readable label to attach}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Attach a readable label to a feed fingerprint shown on the usage dashboard';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hash = $this->resolveHash($this->argument('feed'));

        if ($hash === null) {
            return self::FAILURE;
        }

        $label = $this->argument('label');

        FeedLabel::updateOrCreate(['feed_hash' => $hash], ['label' => $label]);

        $this->info(sprintf('Labeled feed %s as "%s".', substr($hash, 0, 12), $label));

        return self::SUCCESS;
    }

    /**
     * Resolve the command's `feed` argument to a stored 64-character
     * fingerprint, printing an error and returning null when it cannot be.
     */
    private function resolveHash(string $feed): ?string
    {
        if (preg_match('/^[0-9a-f]{64}$/', $feed) === 1) {
            return $feed;
        }

        if (preg_match('/^[0-9a-f]{8,63}$/', $feed) === 1) {
            return $this->resolvePrefix($feed);
        }

        $hash = FeedIdentity::fromUrl($feed)->hash;

        if ($hash === null) {
            $this->error('Could not derive a fingerprint from that feed.');

            return null;
        }

        return $hash;
    }

    /**
     * Resolve a hex prefix to exactly one fingerprint by searching the
     * distinct feed_hash values in usage_events and usage_monthly.
     */
    private function resolvePrefix(string $prefix): ?string
    {
        $matches = $this->matchingHashes($prefix);

        if ($matches->isEmpty()) {
            $this->error(sprintf('No feed matches the prefix "%s".', $prefix));

            return null;
        }

        if ($matches->count() > 1) {
            $this->error(sprintf(
                'The prefix "%s" matches %d feeds; use a longer prefix or the full fingerprint.',
                $prefix,
                $matches->count()
            ));

            return null;
        }

        return $matches->first();
    }

    /**
     * @return Collection<int, string>
     */
    private function matchingHashes(string $prefix): Collection
    {
        $fromEvents = UsageEvent::query()
            ->whereNotNull('feed_hash')
            ->where('feed_hash', 'like', $prefix . '%')
            ->distinct()
            ->pluck('feed_hash');

        $fromMonthly = UsageMonthly::query()
            ->where('feed_hash', '!=', '')
            ->where('feed_hash', 'like', $prefix . '%')
            ->distinct()
            ->pluck('feed_hash');

        return $fromEvents->merge($fromMonthly)->unique()->values();
    }
}
