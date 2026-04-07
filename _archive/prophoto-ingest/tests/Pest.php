<?php

use ProPhoto\Ingest\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case Binding
|--------------------------------------------------------------------------
| Bind the TestCase to all test directories that need Laravel/Testbench
*/

uses(TestCase::class)->in('Feature', 'Integration', 'Jobs', 'Unit', 'Security', 'Performance', 'Database');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
| Custom expectations for common assertions
*/

expect()->extend('toBeValidUuid', function () {
    return $this->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
});

expect()->extend('toBeValidExifDate', function () {
    return $this->toMatch('/^\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}$/');
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
| Global helper functions available in all tests
*/

function fixture(string $path): string
{
    return __DIR__ . '/fixtures/' . $path;
}
