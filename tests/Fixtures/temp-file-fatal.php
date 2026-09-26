<?php

/*
 * Creates a temp file through the controller, prints its path, then dies from memory
 * exhaustion. Run in a child process by TempFileCleanupTest.
 */

require __DIR__ . '/../../vendor/autoload.php';

$controller = new App\Http\Controllers\Controller();
$makeTempFile = Closure::bind(
    fn (string $prefix): string => $this->makeTempFile($prefix),
    $controller,
    App\Http\Controllers\Controller::class
);

echo $makeTempFile('pdf_fatal_test_') . PHP_EOL;

ini_set('memory_limit', '16M');
$exhaustMemory = str_repeat('x', 64 * 1024 * 1024);
