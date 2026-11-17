<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $headers = ['Cache-Control' => 'no-store, private', 'Vary' => 'Authorization'];
        $key = config('status.check_key');

        if (! is_string($key) || $key === '' || ! hash_equals($key, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized.'], 401, $headers);
        }

        $checks = [
            'application' => 'ok',
            'database' => $this->databaseHealthy() ? 'ok' : 'error',
            'storage' => $this->storageHealthy() ? 'ok' : 'error',
        ];
        $healthy = ! in_array('error', $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'checked_at' => now()->toIso8601String(),
        ], $healthy ? 200 : 503, $headers);
    }

    private function databaseHealthy(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function storageHealthy(): bool
    {
        try {
            $disk = Storage::disk('local');
            $path = '.status-'.Str::random(32);

            try {
                $healthy = $disk->put($path, 'ok') && $disk->get($path) === 'ok';
            } finally {
                $removed = $disk->delete($path);
            }

            return $healthy && $removed;
        } catch (Throwable) {
            return false;
        }
    }
}
