<?php

declare(strict_types=1);

return [
    'transport' => env('MAIL_TRANSPORT', 'filesystem'),
    'from' => env('MAIL_FROM', ''),
    'resend_key' => env('RESEND_API_KEY', ''),
    'resend_webhook_secret' => env('RESEND_WEBHOOK_SECRET', ''),
];
