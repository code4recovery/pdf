<?php

namespace Tests\Feature;

use App\Models\FeedLabel;
use App\Models\UsageEvent;
use App\Support\FeedIdentity;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LabelFeedTest extends TestCase
{
    #[Test]
    public function label_by_url(): void
    {
        $url = 'https://example.test/feed.json';
        $hash = FeedIdentity::fromUrl($url)->hash;

        $this->artisan('usage:label', ['feed' => $url, 'label' => 'Example Feed'])
            ->assertExitCode(0);

        $this->assertSame(1, FeedLabel::count());
        $this->assertSame('Example Feed', FeedLabel::find($hash)->label);
    }

    #[Test]
    public function label_by_prefix(): void
    {
        $hash = str_repeat('a', 64);

        UsageEvent::factory()->create(['feed_hash' => $hash]);

        $this->artisan('usage:label', ['feed' => substr($hash, 0, 12), 'label' => 'Prefix Feed'])
            ->assertExitCode(0);

        $this->assertSame(1, FeedLabel::count());
        $this->assertSame('Prefix Feed', FeedLabel::find($hash)->label);
    }

    #[Test]
    public function ambiguous_prefix_fails(): void
    {
        $hashOne = str_repeat('a', 56) . str_repeat('1', 8);
        $hashTwo = str_repeat('a', 56) . str_repeat('2', 8);

        UsageEvent::factory()->create(['feed_hash' => $hashOne]);
        UsageEvent::factory()->create(['feed_hash' => $hashTwo]);

        $this->artisan('usage:label', ['feed' => str_repeat('a', 56), 'label' => 'Ambiguous'])
            ->assertExitCode(1);

        $this->assertSame(0, FeedLabel::count());
    }

    #[Test]
    public function unknown_prefix_fails(): void
    {
        $this->artisan('usage:label', ['feed' => str_repeat('f', 12), 'label' => 'Nobody'])
            ->assertExitCode(1);

        $this->assertSame(0, FeedLabel::count());
    }

    #[Test]
    public function relabel_updates_existing(): void
    {
        $hash = str_repeat('b', 64);

        FeedLabel::create(['feed_hash' => $hash, 'label' => 'Old Name']);

        $this->artisan('usage:label', ['feed' => $hash, 'label' => 'New Name'])
            ->assertExitCode(0);

        $this->assertSame(1, FeedLabel::count());
        $this->assertSame('New Name', FeedLabel::find($hash)->label);
    }

    #[Test]
    public function url_is_not_stored(): void
    {
        $this->assertSame(
            ['feed_hash', 'label', 'created_at', 'updated_at'],
            Schema::getColumnListing('feed_labels')
        );

        $url = 'https://example.test/secret-feed.json?token=abc123';

        $this->artisan('usage:label', ['feed' => $url, 'label' => 'Secret Feed'])
            ->assertExitCode(0);

        $row = FeedLabel::first();

        $this->assertNotNull($row);
        $this->assertSame(64, strlen($row->feed_hash));
        $this->assertStringNotContainsString('secret-feed', $row->feed_hash);
        $this->assertStringNotContainsString('token', $row->feed_hash);
    }
}
