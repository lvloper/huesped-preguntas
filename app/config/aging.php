<?php

return ['poll_ms' => (int) env('AGING_POLL_MS', 5000), 'turnstile_secret' => env('TURNSTILE_SECRET'), 'turnstile_hostname' => env('TURNSTILE_HOSTNAME', 'eventos.huesped.org.ar')];
