<?php

namespace ProPhoto\Ingest\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use ProPhoto\Ingest\IngestServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            IngestServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
