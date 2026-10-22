<?php

// Browser / phone notifications. Generate the keys once with: php artisan postflow:vapid --write
return [
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject' => env('VAPID_SUBJECT', 'mailto:postflow@example.com'),
];
