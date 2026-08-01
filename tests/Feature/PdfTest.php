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
    public function region_day_page_breaks_do_not_emit_a_trailing_blank_page(): void
    {
        $this->fakeMultiRegionFeed();

        $response = $this->get('/pdf?json=https://example.test/regions&group_by=region-day&options[]=pagebreaks');

        $response->assertOk();
        $this->assertSame(
            3,
            $this->countPdfPages($response->getContent()),
            'Expected one page per region (3); an extra page means the final region still emits page-break-after: always.'
        );
    }

    #[Test]
    public function day_region_page_breaks_do_not_emit_a_trailing_blank_page(): void
    {
        $this->fakeMultiRegionFeed();

        $response = $this->get('/pdf?json=https://example.test/regions&group_by=day-region&options[]=pagebreaks');

        $response->assertOk();
        $this->assertSame(2, $this->countPdfPages($response->getContent()), 'Expected one page per day (2).');
    }

    #[Test]
    public function region_day_page_breaks_do_not_emit_blank_pages_on_the_chunked_path(): void
    {
        Http::fake(['example.test/large' => Http::response($this->largeMultiRegionFeed(60), 200)]);

        $response = $this->get('/pdf?json=https://example.test/large&group_by=region-day&options[]=pagebreaks');

        $response->assertOk();
        $this->assertSame(
            60,
            $this->countPdfPages($response->getContent()),
            'Expected one page per region (60). Feeds over 500 meetings render each region as a separate '
            .'PDF and merge with FPDI; without a .region:last-child suppressor every chunk ends with a '
            .'trailing blank page, which the merge preserves — 120 pages, half of them blank.'
        );
    }

    /**
     * A feed large enough to exceed the 500-meeting fast path in Controller::pdf()
     * and exercise the chunked render + FPDI merge instead.
     *
     * @return list<array<string, mixed>>
     */
    protected function largeMultiRegionFeed(int $regions): array
    {
        $meetings = [];

        foreach (range(1, $regions) as $r) {
            $region = 'Region'.str_pad((string) $r, 2, '0', STR_PAD_LEFT);

            foreach ([1, 3, 5] as $day) {
                for ($i = 0; $i < 3; $i++) {
                    $meetings[] = [
                        'slug' => strtolower($region)."-$day-$i",
                        'name' => $region.' AA Group '.$i,
                        'day' => $day,
                        'time' => '19:00',
                        'address' => (100 + $i).' Main St, '.$region.', MS',
                        'regions' => ['Mississippi', 'North', $region],
                        'types' => ['O'],
                    ];
                }
            }
        }

        return $meetings;
    }

    protected function fakeMultiRegionFeed(): void
    {
        Http::fake([
            'example.test/regions' => Http::response(
                json_decode(file_get_contents(base_path('tests/Fixtures/meetings-multi-region.json')), true),
                200
            ),
        ]);
    }

    protected function countPdfPages(string $pdf): int
    {
        return preg_match_all('~/Type\s*/Page[^s]~', $pdf);
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
