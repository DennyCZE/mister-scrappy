<?php

use App\Http\Controllers\NotifyController;
use App\Http\Controllers\ScrapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'running'
    ]);
});
