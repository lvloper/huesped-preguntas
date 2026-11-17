<?php

use App\Http\Controllers\QuestionController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

Route::post('/questions', [QuestionController::class, 'store']);
Route::get('/status', StatusController::class)->name('status');
