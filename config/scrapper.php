<?php

$pages = [];
for ($i = 1; ; $i++) {
    $url = env("SCRAPPER_URL_{$i}");
    if (empty($url)) {
        break;
    }
    $pages[] = [
        'url' => $url,
        'rules' => env("SCRAPPER_RULES_{$i}", ''),
    ];
}

return [
    // Array of pages to scrap. Configure via SCRAPPER_URL_1 / SCRAPPER_RULES_1,
    // SCRAPPER_URL_2 / SCRAPPER_RULES_2, ... in .env
    'pages' => $pages,

    // Timeout in seconds between scrape cycles (default 30 minutes)
    'watcher_timeout' => env('SCRAPPER_WATCH_TIMEOUT', 1800),

    // How many cycles between "still alive" Discord messages
    'watcher_alive_message_period' => env('SCRAPPER_WATCH_ALIVE_MESSAGE_PERIOD', 20),

    'simple_href_format' => env('SCRAPPER_SIMPLE_HREF_FORMAT', false)
];
