<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $db = config('database.connections.'.config('database.default').'.database');
        if (trim($db) === 'lux_monitor') {
            throw new \RuntimeException('Tests must NEVER run against production database lux_monitor!');
        }
    }
}
