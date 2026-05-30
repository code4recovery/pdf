<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomeTest extends TestCase
{
    #[Test]
    public function screen_one_renders_without_json_param(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) =>
                $page->component('Home')
                    ->where('screen', 1)
            );
    }

    #[Test]
    public function screen_two_renders_with_json_param(): void
    {
        Http::fake([
            'example.test/feed' => Http::response(
                json_decode(file_get_contents(base_path('tests/Fixtures/meetings.json')), true),
                200
            ),
        ]);

        $this->get('/?json=https://example.test/feed')
            ->assertOk()
            ->assertInertia(fn ($page) =>
                $page->component('Home')
                    ->where('screen', 2)
                    ->has('availableRegions')
            );
    }

    #[Test]
    public function screen_one_with_error_when_json_fetch_fails(): void
    {
        Http::fake([
            'broken.test/feed' => Http::response(null, 500),
        ]);

        $this->get('/?json=https://broken.test/feed')
            ->assertOk()
            ->assertInertia(fn ($page) =>
                $page->component('Home')
                    ->where('screen', 1)
                    ->has('error')
            );
    }
}
