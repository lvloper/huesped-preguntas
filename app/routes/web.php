<?php

use App\Http\Controllers\QuestionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel');
Route::middleware(['auth', 'can:access-panel'])->group(function () {
    Route::view('/panel', 'panel')->name('dashboard');
    Route::get('/panel/data', [QuestionController::class, 'index']);
    Route::patch('/panel/questions/{question}', [QuestionController::class, 'update']);
});
require __DIR__.'/auth.php';

if (app()->environment('local')) {
    Route::get('/prueba-formulario', fn () => response()->file(base_path('embed/prueba-local.html')));
}
