<?php

use Illuminate\Support\Facades\Route;
use Modules\OpenAiRag\Http\Controllers\OpenAiRagController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('openairags', OpenAiRagController::class)->names('openairag');
});
