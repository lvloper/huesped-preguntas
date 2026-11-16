<?php

namespace App\Services;

use Illuminate\Http\Request;

class SubmissionIdentity
{
    /** Rate-limit key only; an IP does not authenticate an attendee. */
    public function resolve(Request $request): ?string
    {
        return $request->ip() ? 'ip:'.$request->ip() : null;
    }
}
