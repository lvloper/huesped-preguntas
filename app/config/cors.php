<?php

return ['paths' => ['api/questions', 'api/participants/resolve'], 'allowed_methods' => ['POST', 'OPTIONS'], 'allowed_origins' => ['https://eventos.huesped.org.ar'], 'allowed_origins_patterns' => [], 'allowed_headers' => ['Content-Type', 'Accept'], 'exposed_headers' => ['Retry-After'], 'max_age' => 600, 'supports_credentials' => false];
