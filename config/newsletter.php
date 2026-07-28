<?php

declare(strict_types=1);

return [
    'secret' => env('NEWSLETTER_SECRET', env('APP_KEY', '')),
    'confirmation_ttl_hours' => 48,
];
