<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ProfileBackofficeRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('backoffice.profiling.enabled', false)) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $requestStartedAt = microtime(true);
        $queries = [];

        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = [
                'sql' => $query->sql,
                'time' => (float) $query->time,
            ];
        });

        /** @var Response $response */
        $response = $next($request);

        $requestTimeMs = round((microtime(true) - $requestStartedAt) * 1000, 2);
        $queryTimeMs = round(array_sum(array_column($queries, 'time')), 2);
        $queryCount = count($queries);
        $slowQueryThreshold = (float) config('backoffice.profiling.slow_query_ms', 80);
        $slowRequestThreshold = (float) config('backoffice.profiling.slow_request_ms', 250);
        $slowQueries = array_values(array_filter(
            $queries,
            static fn (array $query): bool => $query['time'] >= $slowQueryThreshold,
        ));
        $peakMemoryMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        if (config('backoffice.profiling.response_headers', true)) {
            $response->headers->set('X-Profile-Request-Time-Ms', (string) $requestTimeMs);
            $response->headers->set('X-Profile-Query-Time-Ms', (string) $queryTimeMs);
            $response->headers->set('X-Profile-Query-Count', (string) $queryCount);
            $response->headers->set('X-Profile-Peak-Memory-Mb', (string) $peakMemoryMb);
        }

        if ($requestTimeMs >= $slowRequestThreshold || $slowQueries !== []) {
            Log::channel(config('backoffice.profiling.log_channel', config('logging.default')))->info('backoffice.profile', [
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
                'request_time_ms' => $requestTimeMs,
                'query_time_ms' => $queryTimeMs,
                'query_count' => $queryCount,
                'peak_memory_mb' => $peakMemoryMb,
                'slow_query_count' => count($slowQueries),
                'slow_queries' => array_map(
                    static fn (array $query): array => [
                        'time_ms' => round($query['time'], 2),
                        'sql' => $query['sql'],
                    ],
                    array_slice($slowQueries, 0, 5),
                ),
            ]);
        }

        return $response;
    }
}