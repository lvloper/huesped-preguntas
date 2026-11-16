<?php

use App\Http\Controllers\QuestionController;
use Illuminate\Support\Facades\Route;

Route::post('/questions', [QuestionController::class, 'store']);
