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

    #[Test]
    public function region_day_omits_the_heading_for_meetings_with_no_region(): void
    {
        $meeting = (object) [
            'time_formatted' => '7 am',
            'name' => 'Sun Up Group',
            'location' => null,
            'address' => '127 Front St',
            'regions_formatted' => '',
            'types' => ['O'],
        ];

        $html = view('pdf', [
            'language' => 'en',
            'font' => 'Noto Sans',
            'font_size' => 10,
            'numbering' => false,
            'group_by' => 'region-day',
            'types_in_use' => [],
            'types' => [],
            'options' => [],
            'meeting_types_heading' => 'Meeting Types',
            'days' => collect(),
            'regions' => collect(['' => collect(['MONDAY' => collect([$meeting])])]),
        ])->render();

        $this->assertStringNotContainsString(
            '<span class="heading"></span>',
            preg_replace('~<span class="heading">\s*</span>~', '<span class="heading"></span>', $html),
            'A region-less group must not emit an empty .heading — its border-bottom renders as a stray rule.'
        );
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
