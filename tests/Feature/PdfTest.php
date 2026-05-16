<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PdfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'example.test/feed' => Http::response(
                json_decode(file_get_contents(base_path('tests/Fixtures/meetings.json')), true),
                200
            ),
        ]);
    }

    #[Test]
    public function default_pdf_generation_returns_a_pdf(): void
    {
        $response = $this->get('/pdf?json=https://example.test/feed');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    #[Test]
    #[DataProvider('groupingStrategies')]
    public function pdf_generates_for_each_grouping_strategy(string $strategy): void
    {
        $response = $this->get('/pdf?json=https://example.test/feed&group_by=' . $strategy);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public static function groupingStrategies(): array
    {
        return [
            'day-region' => ['day-region'],
            'region-day' => ['region-day'],
            'day' => ['day'],
        ];
    }

    #[Test]
    public function pdf_generates_with_cjk_language(): void
    {
        $response = $this->get('/pdf?json=https://example.test/feed&language=ja');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function pdf_generates_from_google_sheets_url(): void
    {
        Http::fake([
            'sheets.googleapis.com/*' => Http::response([
                'values' => [
                    ['slug', 'name', 'day', 'time', 'types', 'address'],
                    ['sheets-sun', 'Sunday Sheets Meeting', 'Sunday', '10:00', 'open, discussion', '100 Peachtree St, Atlanta, GA'],
                    ['sheets-mon', 'Monday Sheets Meeting', 'Monday', '19:30', 'closed, men', '200 Peachtree St, Atlanta, GA'],
                    ['sheets-wed', 'Wednesday Sheets Meeting', 'Wednesday', '07:00', 'open, big book', '300 Peachtree St, Atlanta, GA'],
                    ['sheets-sat', 'Saturday Sheets Meeting', 'Saturday', '12:00', 'open, beginners', '400 Peachtree St, Atlanta, GA'],
                ],
            ], 200),
        ]);

        $userFacingUrl = 'https://docs.google.com/spreadsheets/d/12Ga8uwMG4WJ8pZ_SEU7vNETp_aQZ-2yNVsYDFqIwHyE/edit?gid=0#gid=0';

        $response = $this->get('/pdf?json=' . urlencode($userFacingUrl));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
