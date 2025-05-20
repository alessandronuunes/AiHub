<?php

use Illuminate\Support\Facades\Route;
use Modules\AiHub\Http\Controllers\AiHubController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('aihubs', AiHubController::class)->names('aihub');
});
