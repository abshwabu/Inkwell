<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    /**
     * Run comprehensive system and SQLite health checks.
     */
    public function index(): JsonResponse
    {
        $dbConnected = false;
        $journalMode = null;
        $foreignKeys = false;
        $integrity = 'failed';
        $dbError = null;

        try {
            $journalModeRow = DB::select('PRAGMA journal_mode;');
            $journalMode = isset($journalModeRow[0]->journal_mode) ? strtolower($journalModeRow[0]->journal_mode) : null;

            $foreignKeysRow = DB::select('PRAGMA foreign_keys;');
            $foreignKeys = ! empty($foreignKeysRow[0]->foreign_keys);

            $integrityRow = DB::select('PRAGMA quick_check(1);');
            $integrity = $integrityRow[0]->quick_check ?? 'ok';

            $dbConnected = true;
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
        }

        $storageWritable = is_writable(storage_path()) && is_writable(database_path());

        $cacheOk = false;
        try {
            $pingKey = 'health_check_ping_' . uniqid();
            Cache::put($pingKey, 'pong', 5);
            $cacheOk = Cache::get($pingKey) === 'pong';
            Cache::forget($pingKey);
        } catch (\Throwable) {
            $cacheOk = false;
        }

        $isMemory = config('database.connections.sqlite.database') === ':memory:' || app()->environment('testing');
        $journalModeOk = $isMemory ? in_array($journalMode, ['memory', 'wal', 'off', null], true) : ($journalMode === 'wal');

        $isHealthy = $dbConnected
            && $journalModeOk
            && $foreignKeys
            && str_contains(strtolower($integrity), 'ok')
            && $storageWritable
            && $cacheOk;

        $statusCode = $isHealthy ? 200 : 503;

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'sqlite' => [
                'connected' => $dbConnected,
                'journal_mode' => $journalMode,
                'foreign_keys' => $foreignKeys,
                'integrity' => $integrity,
                'error' => $dbError,
            ],
            'storage' => [
                'writable' => $storageWritable,
            ],
            'cache' => [
                'working' => $cacheOk,
            ],
        ], $statusCode);
    }
}
