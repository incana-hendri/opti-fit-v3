<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Run DatabaseSeeder after each refresh, so the settings row (BR-9.1) always exists.
     */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests never depend on a Vite build.
        $this->withoutVite();
    }
}
