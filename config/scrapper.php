<?php

return [
    'url' => env('SCRAPPER_URL', 'https://google.com/'),

    // JSON rules
    'rules' => env('SCRAPPER_RULES', ''),

    // Timeout in seconds (default 30 minutes)
    'watcher_timeout' => env('SCRAPPER_WATCH_TIMEOUT', 1800),
];
