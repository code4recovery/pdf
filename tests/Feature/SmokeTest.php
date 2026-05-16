<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    #[Test]
    public function artisan_about_boots_clean(): void
    {
        $this->artisan('about')->assertSuccessful();
    }

    #[Test]
    public function package_discover_succeeds(): void
    {
        $this->artisan('package:discover')->assertSuccessful();
    }
}
