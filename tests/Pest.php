<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Feature tests run against MariaDB (the `testing` database), never SQLite (docs/07).
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
