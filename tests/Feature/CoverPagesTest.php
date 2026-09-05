<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class CoverPagesTest extends TestCase
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
    public function fpdi_reads_a_pdf_with_compressed_cross_reference_streams(): void
    {
        $reader = new Fpdi('P', 'pt');

        $pageCount = $reader->setSourceFile(base_path('tests/Fixtures/cover-xref-stream.pdf'));
        $size = $reader->getTemplateSize($reader->importPage(1));

        $this->assertSame(1, $pageCount);
        $this->assertEqualsWithDelta(306.0, $size['width'], 0.5);
        $this->assertEqualsWithDelta(792.0, $size['height'], 0.5);
    }

    #[Test]
    public function pdf_route_accepts_post_without_covers(): void
    {
        $response = $this->post('/pdf', ['json' => 'https://example.test/feed']);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    #[Test]
    public function pdf_route_still_accepts_get(): void
    {
        $this->get('/pdf?json=https://example.test/feed')->assertOk();
    }
}
