<?php

use App\Http\Controllers\NotifyController;
use App\Http\Controllers\ScrapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'running'
    ]);
});

Route::get('/scrap/test', [ScrapController::class, 'test'])->name('scrap.test');

Route::get('/notify/discord-test', [NotifyController::class, 'discordTest'])->name('notify.discord-test');
