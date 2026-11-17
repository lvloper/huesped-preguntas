<?php

use App\Http\Controllers\QuestionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel');
Route::get('/formulario', function () {
    return response()->view('formulario', [
        'formHtml' => file_get_contents(base_path('embed/formulario.html')),
    ])->withHeaders([
        'Content-Security-Policy' => "frame-ancestors 'self' https://eventos.huesped.org.ar",
        'Cache-Control' => 'no-store, private',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ]);
})->name('formulario');
Route::middleware(['auth', 'can:access-panel'])->group(function () {
    Route::view('/panel', 'panel')->name('dashboard');
    Route::get('/panel/data', [QuestionController::class, 'index']);
    Route::patch('/panel/questions/{question}', [QuestionController::class, 'update']);
});
require __DIR__.'/auth.php';

if (app()->environment('local')) {
    Route::get('/prueba-formulario', fn () => response()->file(base_path('embed/prueba-local.html')));
}
