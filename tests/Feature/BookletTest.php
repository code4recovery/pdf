<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class BookletTest extends TestCase
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
    public function small_directory_booklet_sheets_are_double_width(): void
    {
        $insidePages = $this->countPdfPages($this->get('/pdf?json=https://example.test/feed')->getContent());

        $response = $this->get('/pdf?json=https://example.test/feed&booklet=1');

        $response->assertOk();
        $sheets = (int) ceil($insidePages / 4);
        $this->assertSame((string) $sheets, $response->headers->get('X-Booklet-Sheets'));
        $this->assertSame(2 * $sheets, $this->countPdfPages($response->getContent()));
        $this->assertStringContainsString('_directory-booklet.pdf', $response->headers->get('Content-Disposition'));

        $size = $this->sheetSize($response->getContent());
        $this->assertEqualsWithDelta(612.0, $size['width'], 0.5);
        $this->assertEqualsWithDelta(792.0, $size['height'], 0.5);
    }

    #[Test]
    public function booklet_with_covers_counts_front_inside_and_back(): void
    {
        $insidePages = $this->countPdfPages($this->get('/pdf?json=https://example.test/feed')->getContent());

        $response = $this->post('/pdf', [
            'json' => 'https://example.test/feed',
            'booklet' => '1',
            'front' => $this->upload('front.pdf', $this->coverPdf('FRONT-COVER-MARK')),
            'back' => $this->upload('back.pdf', $this->coverPdf('BACK-COVER-MARK', 306, 792, 2)),
        ]);

        $response->assertOk();
        $this->assertSame((string) (int) ceil((1 + $insidePages + 2) / 4), $response->headers->get('X-Booklet-Sheets'));
        $this->assertStringContainsString('FRONT-COVER-MARK', $response->getContent());
        $this->assertStringContainsString('BACK-COVER-MARK', $response->getContent());
    }

    #[Test]
    public function large_directory_booklet_on_the_chunked_path(): void
    {
        Http::fake(['example.test/large' => Http::response($this->largeMultiRegionFeed(60), 200)]);

        $response = $this->get('/pdf?json=https://example.test/large&group_by=region-day&options[]=pagebreaks&booklet=1');

        $response->assertOk();
        $this->assertSame('15', $response->headers->get('X-Booklet-Sheets'));
        $this->assertSame(30, $this->countPdfPages($response->getContent()));

        $size = $this->sheetSize($response->getContent());
        $this->assertEqualsWithDelta(612.0, $size['width'], 0.5);
        $this->assertEqualsWithDelta(792.0, $size['height'], 0.5);
    }

    #[Test]
    public function wide_pages_produce_landscape_sheets(): void
    {
        $response = $this->get('/pdf?json=https://example.test/feed&width=8.5&height=5.5&booklet=1');

        $response->assertOk();
        $size = $this->sheetSize($response->getContent());
        $this->assertEqualsWithDelta(1224.0, $size['width'], 0.5);
        $this->assertEqualsWithDelta(396.0, $size['height'], 0.5);
    }

    #[Test]
    public function stream_mode_returns_an_inline_booklet(): void
    {
        $response = $this->get('/pdf?json=https://example.test/feed&mode=stream&booklet=1');

        $response->assertOk();
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('-booklet.pdf', $response->headers->get('Content-Disposition'));
        $this->assertTrue($response->headers->has('X-Booklet-Sheets'));
    }

    #[Test]
    public function booklet_zero_is_the_normal_output(): void
    {
        $response = $this->get('/pdf?json=https://example.test/feed&booklet=0');

        $response->assertOk();
        $this->assertFalse($response->headers->has('X-Booklet-Sheets'));

        $size = $this->sheetSize($response->getContent());
        $this->assertEqualsWithDelta(306.0, $size['width'], 0.5);
        $this->assertEqualsWithDelta(792.0, $size['height'], 0.5);
    }

    /**
     * The size in points of page 1 of a PDF.
     *
     * @return array{width: float, height: float}
     */
    private function sheetSize(string $pdf): array
    {
        $path = tempnam(sys_get_temp_dir(), 'booklet_test_');

        try {
            file_put_contents($path, $pdf);
            $reader = new Fpdi('P', 'pt');
            $reader->setSourceFile($path);
            $size = $reader->getTemplateSize($reader->importPage(1));

            return ['width' => (float) $size['width'], 'height' => (float) $size['height']];
        } finally {
            unlink($path);
        }
    }
}
