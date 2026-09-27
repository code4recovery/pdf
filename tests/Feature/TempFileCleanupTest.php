<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class TempFileCleanupTest extends TestCase
{
    #[Test]
    public function temp_file_is_deleted_when_php_dies_from_a_fatal_error(): void
    {
        $result = Process::path(base_path())->run([PHP_BINARY, 'tests/Fixtures/temp-file-fatal.php']);

        $path = strtok($result->output(), PHP_EOL);

        $this->assertTrue($result->failed());
        $this->assertStringContainsString('Allowed memory size', $result->output() . $result->errorOutput());
        $this->assertStringContainsString('pdf_fatal_test_', $path);
        $this->assertFileDoesNotExist($path);
    }

    #[Test]
    public function temp_file_is_deleted_after_a_successful_merge(): void
    {
        Http::fake([
            'example.test/feed' => Http::response(
                json_decode(file_get_contents(base_path('tests/Fixtures/meetings.json')), true),
                200
            ),
        ]);

        $before = glob(sys_get_temp_dir() . '/pdf_inside_*');

        $response = $this->post('/pdf', [
            'json' => 'https://example.test/feed',
            'front' => UploadedFile::fake()->createWithContent('front.pdf', $this->letterHalfPdf()),
        ]);

        $response->assertOk();
        $this->assertSame($before, glob(sys_get_temp_dir() . '/pdf_inside_*'));
    }

    private function letterHalfPdf(): string
    {
        $pdf = new Fpdi('P', 'pt', [306, 792]);
        $pdf->AddPage();

        return $pdf->Output('S');
    }
}
