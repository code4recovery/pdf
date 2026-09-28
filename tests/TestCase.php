<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

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

    protected function countPdfPages(string $pdf): int
    {
        return preg_match_all('~/Type\s*/Page[^s]~', $pdf);
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
}
