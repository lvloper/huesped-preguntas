<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Services\SubmissionIdentity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class QuestionController extends Controller
{
    public function store(Request $request, SubmissionIdentity $identity): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'email' => 'required|email|max:255', 'question' => 'required|string|max:5000', 'cf-turnstile-response' => 'required|string|max:2048']);
        $key = $identity->resolve($request);
        abort_if(! $key || ! config('aging.turnstile_secret'), 503, 'La recepción todavía no está habilitada.');
        $dummyKey = config('aging.turnstile_secret') === '1x0000000000000000000000000000000AA';
        abort_if($dummyKey && ! app()->environment('local'), 503, 'Las claves de prueba solo están habilitadas en local.');
        try {
            $verification = Http::asForm()->connectTimeout(3)->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', ['secret' => config('aging.turnstile_secret'), 'response' => $data['cf-turnstile-response']]);
        } catch (ConnectionException $e) {
            return response()->json(['message' => 'No se pudo verificar. Intentá nuevamente.'], 503);
        }
        if (! $verification->successful() || $verification->json('success') !== true || ($dummyKey ? $verification->json('hostname') !== 'example.com' : ($verification->json('hostname') !== config('aging.turnstile_hostname') || $verification->json('action') !== 'question'))) {
            return response()->json(['message' => 'Verificación inválida. Intentá nuevamente.'], 422);
        }
        $cacheKey = 'question:'.hash('sha256', $key);
        $lock = Cache::lock($cacheKey.':lock', 15);
        if (! $lock->get()) {
            return response()->json(['message' => 'Hay un envío en curso.', 'retry_after' => 1], 429)->header('Retry-After', '1');
        }
        try {
            $remaining = RateLimiter::availableIn($cacheKey);
            if ($remaining > 0) {
                return response()->json(['message' => 'Esperá antes de volver a preguntar.', 'retry_after' => $remaining], 429)->header('Retry-After', (string) $remaining);
            }
            Question::create(collect($data)->only(['name', 'email', 'question'])->all());
            RateLimiter::hit($cacheKey, 30);

            return response()->json(['message' => 'Pregunta enviada.', 'retry_after' => 30], 201);
        } finally {
            $lock->release();
        }
    }

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('answered')) {
            return response()->json(['answered' => Question::where('answered', true)->orderByDesc('id')->paginate(20)])->header('Cache-Control', 'no-store');
        }

        return response()->json(['received' => Question::orderBy('id')->paginate(20), 'selected' => Question::where('selected', true)->where('answered', false)->orderBy('id')->get()])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, Question $question): JsonResponse
    {
        $data = $request->validate(['selected' => 'sometimes|required|boolean', 'answered' => 'sometimes|required|boolean']);
        $question->update($data);

        return response()->json($question)->header('Cache-Control', 'no-store');
    }
}
