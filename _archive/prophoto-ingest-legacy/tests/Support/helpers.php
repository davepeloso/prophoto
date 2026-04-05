<?php

use Illuminate\Support\Facades\Config;

if (!function_exists('suiteEnabled')) {
    /**
     * Check if a test suite is enabled via config
     */
    function suiteEnabled(string $suite): bool
    {
        return (bool) Config::get("ingest-tests.switchboard.{$suite}", true);
    }
}

if (!function_exists('skipIfDisabled')) {
    /**
     * Skip the current test if its suite is disabled
     */
    function skipIfDisabled(string $suite): void
    {
        if (!suiteEnabled($suite)) {
            test()->markTestSkipped("{$suite} suite disabled via ingest-tests config");
        }
    }
}
