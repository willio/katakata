<?php

declare(strict_types=1);

return [
    'secret' => env('ANALYTICS_SECRET', env('APP_KEY', '')),
    'retention_days' => 400,
];
