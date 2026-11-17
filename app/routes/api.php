<?php

use App\Http\Controllers\ParticipantLookupController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

Route::post('/questions', [QuestionController::class, 'store']);
Route::post('/participants/resolve', ParticipantLookupController::class)->middleware('throttle:600,1');
Route::get('/status', StatusController::class)->name('status');
