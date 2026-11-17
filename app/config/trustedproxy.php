<?php

return [
    // Explicit proxy addresses only. Never trust arbitrary client forwarding headers.
    'proxies' => array_values(array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))))),
];
