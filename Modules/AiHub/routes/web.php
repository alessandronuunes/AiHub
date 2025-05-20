<?php

use Illuminate\Support\Facades\Route;
use Modules\AiHub\Http\Controllers\AiHubController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('aihubs', AiHubController::class)->names('aihub');
});
