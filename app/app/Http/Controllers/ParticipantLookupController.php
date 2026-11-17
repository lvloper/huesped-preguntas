<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipantLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['uid' => 'required|string|size:40|alpha_num:ascii']);
        $participant = Participant::where('uid', $data['uid'])->first();

        if (! $participant) {
            return response()->json(['message' => 'No encontramos los datos de este enlace.'], 404)->header('Cache-Control', 'no-store');
        }

        return response()->json($participant->only(['name', 'email']))->header('Cache-Control', 'no-store');
    }
}
