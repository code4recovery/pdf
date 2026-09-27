<?php

namespace Tests\Feature;

use App\Support\FeedIdentity;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedIdentityTest extends TestCase
{
    #[Test]
    public function sheet_variants_share_a_fingerprint(): void
    {
        $edit = FeedIdentity::fromUrl('https://docs.google.com/spreadsheets/d/ABC/edit#gid=0');
        $sharing = FeedIdentity::fromUrl('https://docs.google.com/spreadsheets/d/ABC/edit?usp=sharing');
        $bare = FeedIdentity::fromUrl('https://docs.google.com/spreadsheets/d/ABC');

        $this->assertSame($edit->hash, $sharing->hash);
        $this->assertSame($edit->hash, $bare->hash);

        foreach ([$edit, $sharing, $bare] as $identity) {
            $this->assertSame('google_sheet', $identity->sourceType);
            $this->assertSame('docs.google.com', $identity->host);
        }
    }

    #[Test]
    public function fingerprint_never_contains_the_sheet_id(): void
    {
        $identity = FeedIdentity::fromUrl('https://docs.google.com/spreadsheets/d/ABC/edit#gid=0');

        $this->assertStringNotContainsString('ABC', $identity->hash);
        $this->assertSame(64, strlen($identity->hash));
    }

    #[Test]
    public function scheme_www_trailing_slash_and_query_order_are_ignored(): void
    {
        $withWww = FeedIdentity::fromUrl('http://www.Example.org/feed/?b=2&a=1');
        $canonical = FeedIdentity::fromUrl('https://example.org/feed?a=1&b=2');

        $this->assertSame($canonical->hash, $withWww->hash);
        $this->assertSame('example.org', $withWww->host);
        $this->assertSame('example.org', $canonical->host);
    }

    #[Test]
    public function tsml_admin_ajax_is_detected(): void
    {
        $identity = FeedIdentity::fromUrl('https://example.org/wp-admin/admin-ajax.php?action=meetings');

        $this->assertSame('tsml', $identity->sourceType);
    }

    #[Test]
    public function other_json_is_json(): void
    {
        $identity = FeedIdentity::fromUrl('https://example.org/meetings.json');

        $this->assertSame('json', $identity->sourceType);
    }

    #[Test]
    public function empty_input_is_none(): void
    {
        $null = FeedIdentity::fromUrl(null);
        $empty = FeedIdentity::fromUrl('');

        $this->assertNull($null->hash);
        $this->assertNull($null->host);
        $this->assertSame('none', $null->sourceType);

        $this->assertNull($empty->hash);
        $this->assertNull($empty->host);
        $this->assertSame('none', $empty->sourceType);
    }

    #[Test]
    public function unparseable_input_hashes_the_raw_trimmed_string(): void
    {
        $identity = FeedIdentity::fromUrl('  not a url  ');

        $this->assertSame(hash('sha256', 'not a url'), $identity->hash);
        $this->assertNull($identity->host);
        $this->assertSame('json', $identity->sourceType);
    }

    #[Test]
    public function host_over_253_chars_is_null_but_hash_still_computed(): void
    {
        $longHost = str_repeat('a', 250) . '.com';

        $identity = FeedIdentity::fromUrl('https://' . $longHost . '/feed');

        $this->assertNull($identity->host);
        $this->assertNotNull($identity->hash);
        $this->assertSame(64, strlen($identity->hash));
    }
}
