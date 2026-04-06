<?php

namespace ProPhoto\Debug\Facades;

use Illuminate\Support\Facades\Facade;
use ProPhoto\Debug\Services\IngestTracer;

/**
 * @method static string startSession(string $uuid)
 * @method static void recordAttempt(string $sessionId, array $data)
 * @method static void recordSuccess(string $sessionId, string $method, array $info = [])
 * @method static void recordFailure(string $sessionId, string $method, string $reason)
 * @method static \Illuminate\Support\Collection getTrace(string $uuid)
 * @method static \Illuminate\Support\Collection getSessionTrace(string $sessionId)
 *
 * @see \ProPhoto\Debug\Services\IngestTracer
 */
class IngestDebug extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IngestTracer::class;
    }
}
