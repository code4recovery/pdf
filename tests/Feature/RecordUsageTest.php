<?php

namespace Tests\Feature;

use App\Models\UsageEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecordUsageTest extends TestCase
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
    public function successful_pdf_records_one_row(): void
    {
        $response = $this->get('/pdf?json=https://example.test/feed&group_by=region-day&language=es');

        $response->assertOk();
        $this->assertSame(1, UsageEvent::count());

        $event = UsageEvent::first();
        $this->assertSame('pdf_generated', $event->event);
        $this->assertSame('success', $event->outcome);
        $this->assertSame(200, $event->http_status);
        $this->assertSame('json', $event->source_type);
        $this->assertSame('example.test', $event->feed_host);
        $this->assertGreaterThan(0, $event->meeting_count);
        $this->assertFalse($event->chunked);
        $this->assertSame('region-day', $event->settings['group_by']);
        $this->assertSame('es', $event->settings['language']);
    }

    #[Test]
    public function row_never_contains_the_feed_url(): void
    {
        Http::fake([
            'sheets.googleapis.com/*' => Http::response([
                'values' => [
                    ['slug', 'name', 'day', 'time', 'types', 'address'],
                    ['sheets-sun', 'Sunday Sheets Meeting', 'Sunday', '10:00', 'open, discussion', '100 Peachtree St, Atlanta, GA'],
                ],
            ], 200),
        ]);

        $sheetId = '12Ga8uwMG4WJ8pZ_SEU7vNETp_aQZ-2yNVsYDFqIwHyE';
        $userFacingUrl = 'https://docs.google.com/spreadsheets/d/' . $sheetId . '/edit?gid=0#gid=0';

        $response = $this->get('/pdf?json=' . urlencode($userFacingUrl));

        $response->assertOk();

        $encoded = json_encode(UsageEvent::first());
        $this->assertStringNotContainsString($sheetId, $encoded);
        $this->assertStringNotContainsString('docs.google.com/spreadsheets/d/', $encoded);
    }

    #[Test]
    public function large_feed_is_marked_chunked(): void
    {
        Http::fake(['example.test/large' => Http::response($this->largeMultiRegionFeed(60), 200)]);

        $response = $this->get('/pdf?json=https://example.test/large&group_by=region-day');

        $response->assertOk();

        $event = UsageEvent::first();
        $this->assertTrue($event->chunked);
        $this->assertGreaterThan(500, $event->meeting_count);
    }

    #[Test]
    public function fetch_failure_is_recorded(): void
    {
        Http::fake(['broken.test/feed' => Http::response(null, 401)]);

        $response = $this->get('/pdf?json=https://broken.test/feed');

        $response->assertStatus(422);

        $event = UsageEvent::first();
        $this->assertSame('fetch_failed', $event->outcome);
        $this->assertSame(422, $event->http_status);
        $this->assertSame(401, $event->upstream_status);
    }

    #[Test]
    public function parse_failure_is_recorded(): void
    {
        Http::fake(['broken.test/feed' => Http::response('not json', 200)]);

        $response = $this->get('/pdf?json=https://broken.test/feed');

        $response->assertStatus(422);
        $this->assertSame('parse_failed', UsageEvent::first()->outcome);
    }

    #[Test]
    public function cover_rejection_is_recorded(): void
    {
        $response = $this->post('/pdf', [
            'json' => 'https://example.test/feed',
            'width' => 4.25,
            'height' => 11,
            'front' => $this->coverUpload('front.pdf', 612, 792),
        ]);

        $response->assertStatus(422);
        $this->assertSame('cover_rejected', UsageEvent::first()->outcome);
    }

    #[Test]
    public function uncaught_exception_is_recorded_as_error(): void
    {
        Route::get('/usage-test-throw', function () {
            throw new \RuntimeException('boom');
        })->middleware('usage')->name('pdf');

        $response = $this->get('/usage-test-throw');

        $response->assertStatus(500);

        $event = UsageEvent::first();
        $this->assertSame('error', $event->outcome);
        $this->assertSame(500, $event->http_status);
    }

    #[Test]
    public function array_settings_are_normalised(): void
    {
        $response = $this->get('/pdf?json=https://example.test/feed&mode[]=x&options[]=' . urlencode('<script>'));

        $response->assertOk();

        $settings = UsageEvent::first()->settings;

        $this->assertSame('other', $settings['mode']);
        $this->assertSame([], $settings['options']);
    }

    /**
     * `language[]=x` is unrelated pre-existing behaviour, same as the
     * scalar `language=xx` case below: Controller::pdf() passes the raw
     * request value straight into Code4Recovery\Spec::getTypesByLanguage(),
     * so a non-string language always fails regardless of usage recording.
     */
    #[Test]
    public function array_language_is_recorded_as_other_even_when_the_request_fails(): void
    {
        $response = $this->get('/pdf?json=https://example.test/feed&language[]=x');

        $response->assertStatus(500);
        $this->assertSame('other', UsageEvent::first()->settings['language']);
    }

    #[Test]
    public function unknown_scalar_settings_become_other(): void
    {
        $response = $this->get('/pdf?json=https://example.test/feed&group_by=diagonal&font=comic-sans&mode=fax&width=4.25abc&font_size=13');

        $response->assertOk();

        $settings = UsageEvent::first()->settings;

        $this->assertSame('other', $settings['group_by']);
        $this->assertSame('other', $settings['font']);
        $this->assertSame('other', $settings['mode']);
        $this->assertSame('other', $settings['paper']);
        $this->assertNull($settings['font_size']);
    }

    /**
     * `language=xx` is unrelated pre-existing behaviour: Controller::pdf()
     * indexes its `$strings` translation table directly by language and
     * has no fallback, so an unrecognised language 500s regardless of usage
     * recording. That's out of scope here — this only proves the recorder
     * still normalises and persists a sane row when that happens.
     */
    #[Test]
    public function unknown_language_is_recorded_as_other_even_when_the_request_fails(): void
    {
        $response = $this->get('/pdf?json=https://example.test/feed&language=xx');

        $response->assertStatus(500);
        $this->assertSame('other', UsageEvent::first()->settings['language']);
    }

    #[Test]
    public function form_opened_is_recorded_with_referrer(): void
    {
        $response = $this->withHeaders(['Referer' => 'https://www.district9.org/meetings/'])
            ->get('/?json=https://example.test/feed');

        $response->assertOk();

        $event = UsageEvent::first();
        $this->assertSame('form_opened', $event->event);
        $this->assertSame('district9.org', $event->referrer_host);
        $this->assertNull($event->settings);
    }

    #[Test]
    public function own_site_referrer_is_dropped(): void
    {
        // Use an absolute request URL matching the Referer's host: this app's
        // own host in .env is c4rpdf.test, not the test client's implicit
        // default of "localhost", so the request must say so explicitly.
        $response = $this->withHeaders(['Referer' => 'http://localhost/'])
            ->get('http://localhost/?json=https://example.test/feed');

        $response->assertOk();
        $this->assertNull(UsageEvent::first()->referrer_host);
    }

    #[Test]
    public function screen_one_is_not_recorded(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertSame(0, UsageEvent::count());
    }

    #[Test]
    public function database_failure_does_not_break_the_pdf(): void
    {
        Schema::drop('usage_events');
        Exceptions::fake();

        $response = $this->get('/pdf?json=https://example.test/feed');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        Exceptions::assertReported(QueryException::class);
    }

    /**
     * Build a one-page PDF at the given size in points, mirroring
     * CoverPagesTest::coverPdf() for the wrong-size-cover scenario.
     */
    protected function coverUpload(string $name, float $width, float $height): UploadedFile
    {
        $pdf = new \FPDF('P', 'pt', [$width, $height]);
        $pdf->SetCompression(false);
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 24);
        $pdf->Cell(0, 30, 'FRONT');

        return UploadedFile::fake()->createWithContent($name, $pdf->Output('S'));
    }
}
