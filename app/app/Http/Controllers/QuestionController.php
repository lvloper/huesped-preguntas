<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\Question;
use App\Services\SubmissionIdentity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class QuestionController extends Controller
{
    public function store(Request $request, SubmissionIdentity $identity): JsonResponse
    {
        $data = $request->validate(['uid' => 'sometimes|required|string|size:40|alpha_num:ascii', 'name' => 'exclude_with:uid|required_without:uid|string|max:150', 'email' => 'exclude_with:uid|required_without:uid|email|max:255', 'question' => 'required|string|max:5000', 'cf-turnstile-response' => 'required|string|max:2048']);
        $participant = null;
        if (isset($data['uid'])) {
            $participant = Participant::where('uid', $data['uid'])->first();
            if (! $participant) {
                throw ValidationException::withMessages(['uid' => 'No encontramos los datos de este enlace.']);
            }
            $data['name'] = $participant->name;
            $data['email'] = $participant->email;
        }
        $key = $identity->resolve($request);
        abort_if(! $key || ! config('aging.turnstile_secret'), 503, 'La recepción todavía no está habilitada.');
        $dummyKey = config('aging.turnstile_secret') === '1x0000000000000000000000000000000AA';
        abort_if($dummyKey && ! app()->environment('local'), 503, 'Las claves de prueba solo están habilitadas en local.');
        try {
            $verification = Http::asForm()->connectTimeout(3)->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', ['secret' => config('aging.turnstile_secret'), 'response' => $data['cf-turnstile-response']]);
        } catch (ConnectionException $e) {
            return response()->json(['message' => 'No se pudo verificar. Intentá nuevamente.'], 503);
        }
        $allowedHostnames = array_filter([config('aging.turnstile_hostname'), config('aging.turnstile_iframe_hostname')]);
        if (! $verification->successful() || $verification->json('success') !== true || ($dummyKey ? $verification->json('hostname') !== 'example.com' : (! in_array($verification->json('hostname'), $allowedHostnames, true) || $verification->json('action') !== 'question'))) {
            return response()->json(['message' => 'Verificación inválida. Intentá nuevamente.'], 422);
        }
        $cacheKey = 'question:'.hash('sha256', $key);
        $lock = Cache::lock($cacheKey.':lock', 15);
        if (! $lock->get()) {
            return response()->json(['message' => 'Hay un envío en curso.', 'retry_after' => 1], 429)->header('Retry-After', '1');
        }
        try {
            if (RateLimiter::tooManyAttempts($cacheKey, 2)) {
                $remaining = RateLimiter::availableIn($cacheKey);

                return response()->json(['message' => 'Esperá antes de volver a preguntar.', 'retry_after' => $remaining], 429)->header('Retry-After', (string) $remaining);
            }
            Question::create([...collect($data)->only(['name', 'email', 'question'])->all(), 'participant_id' => $participant?->id]);
            $attempts = RateLimiter::attempts($cacheKey) + 1;
            RateLimiter::clear($cacheKey);
            RateLimiter::increment($cacheKey, 30, $attempts);

            return response()->json(['message' => 'Pregunta enviada.', 'retry_after' => $attempts >= 2 ? 30 : 0], 201);
        } finally {
            $lock->release();
        }
    }

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('discarded')) {
            return response()->json(['discarded' => Question::onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id')->paginate(20)])->header('Cache-Control', 'no-store');
        }

        if ($request->boolean('answered')) {
            return response()->json(['answered' => Question::where('answered', true)->orderByDesc('id')->paginate(20)])->header('Cache-Control', 'no-store');
        }

        $data = $request->validate(['order' => 'sometimes|required|in:asc,desc']);

        return response()->json(['received' => Question::where('selected', false)->orderBy('id', $data['order'] ?? 'asc')->paginate(20), 'selected' => Question::where('selected', true)->where('answered', false)->orderBy('id')->get(), 'discarded_count' => Question::onlyTrashed()->count()])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, Question $question): JsonResponse
    {
        $data = $request->validate(['selected' => 'sometimes|required|boolean', 'answered' => 'sometimes|required|boolean']);
        $question->update($data);

        return response()->json($question)->header('Cache-Control', 'no-store');
    }

    public function destroy(Question $question): Response|JsonResponse
    {
        $lock = Cache::lock('questions:discard', 15);
        if (! $lock->get()) {
            return response()->json(['message' => 'Hay un descarte en curso. Intentá nuevamente.'], 429)->header('Retry-After', '1');
        }

        try {
            DB::transaction(function () use ($question): void {
                $question->delete();
                $excess = Question::onlyTrashed()->count() - 100;
                if ($excess > 0) {
                    $expiredIds = Question::onlyTrashed()->orderBy('deleted_at')->orderBy('id')->limit($excess)->pluck('id');
                    Question::onlyTrashed()->whereKey($expiredIds)->forceDelete();
                }
            });

            return response()->noContent()->header('Cache-Control', 'no-store');
        } finally {
            $lock->release();
        }
    }
}
