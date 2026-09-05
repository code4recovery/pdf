<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
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

    /**
     * Build a one-or-more page PDF in memory at the given size in points, with the label
     * left uncompressed so it can be located in merged output.
     */
    protected function coverPdf(string $label, float $width = 306, float $height = 792, int $pages = 1, string $orientation = 'P'): string
    {
        $pdf = new \FPDF($orientation, 'pt', [$width, $height]);
        $pdf->SetCompression(false);
        $pdf->SetFont('Helvetica', '', 24);
        for ($i = 0; $i < $pages; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 30, $label);
        }

        return $pdf->Output('S');
    }

    protected function upload(string $name, string $bytes): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    #[Test]
    public function letter_sized_cover_is_rejected_for_a_half_letter_directory(): void
    {
        $response = $this->post('/pdf', [
            'json' => 'https://example.test/feed',
            'width' => 4.25,
            'height' => 11,
            'front' => $this->upload('front.pdf', $this->coverPdf('FRONT', 612, 792)),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('front cover is 8.5 x 11 in', $response->getContent());
        $this->assertStringContainsString('directory is 4.25 x 11 in', $response->getContent());
        Http::assertNothingSent();
    }

    #[Test]
    public function landscape_cover_with_swapped_dimensions_is_rejected(): void
    {
        $response = $this->post('/pdf', [
            'json' => 'https://example.test/feed',
            'front' => $this->upload('front.pdf', $this->coverPdf('FRONT', 306, 792, 1, 'L')),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('front cover is 11 x 4.25 in', $response->getContent());
    }

    #[Test]
    public function mismatch_on_a_later_page_is_rejected_and_names_the_page(): void
    {
        $good = $this->coverPdf('A');
        $bad = $this->coverPdf('B', 612, 792);
        $merger = new Fpdi('P', 'pt');
        foreach ([$good, $bad] as $bytes) {
            $merger->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($bytes));
            $tpl = $merger->importPage(1);
            $size = $merger->getTemplateSize($tpl);
            $merger->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $merger->useTemplate($tpl);
        }

        $response = $this->post('/pdf', [
            'json' => 'https://example.test/feed',
            'back' => $this->upload('back.pdf', $merger->Output('S')),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('back cover is 8.5 x 11 in (page 2)', $response->getContent());
    }

    #[Test]
    public function cover_within_an_eighth_of_an_inch_is_accepted(): void
    {
        $response = $this->post('/pdf', [
            'json' => 'https://example.test/feed',
            'front' => $this->upload('front.pdf', $this->coverPdf('FRONT', 306 + 8, 792 - 8)),
        ]);

        $response->assertOk();
    }

    #[Test]
    public function non_pdf_upload_is_rejected(): void
    {
        $response = $this->post('/pdf', [
            'json' => 'https://example.test/feed',
            'front' => $this->upload('front.pdf', "not a pdf\n"),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('front cover must be a PDF', $response->getContent());
        Http::assertNothingSent();
    }

    #[Test]
    public function oversized_upload_is_rejected(): void
    {
        $response = $this->post('/pdf', [
            'json' => 'https://example.test/feed',
            'back' => $this->upload('back.pdf', $this->coverPdf('BACK') . str_repeat('%', 5 * 1024 * 1024)),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('back cover must be 5 MB or smaller', $response->getContent());
        Http::assertNothingSent();
    }

    #[Test]
    public function cover_with_too_many_pages_is_rejected(): void
    {
        $response = $this->post('/pdf', [
            'json' => 'https://example.test/feed',
            'front' => $this->upload('front.pdf', $this->coverPdf('FRONT', 306, 792, 11)),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('front cover has 11 pages; the limit is 10', $response->getContent());
        Http::assertNothingSent();
    }

    #[Test]
    public function password_protected_cover_is_rejected_with_a_password_hint(): void
    {
        $response = $this->post('/pdf', [
            'json' => 'https://example.test/feed',
            'front' => $this->upload('front.pdf', file_get_contents(base_path('tests/Fixtures/cover-encrypted.pdf'))),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('password', $response->getContent());
        Http::assertNothingSent();
    }
}
