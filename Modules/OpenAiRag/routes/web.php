<?php

use Illuminate\Support\Facades\Route;
use Modules\OpenAiRag\Http\Controllers\OpenAiRagController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('openairags', OpenAiRagController::class)->names('openairag');
});
