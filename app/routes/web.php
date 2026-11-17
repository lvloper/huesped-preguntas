<?php

use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\QuestionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel');
Route::get('/formulario', function () {
    return response()->view('formulario', [
        'formHtml' => file_get_contents(base_path('embed/formulario.html')),
    ])->withHeaders([
        'Content-Security-Policy' => "frame-ancestors 'self' https://eventos.huesped.org.ar",
        'Cache-Control' => 'no-store, private',
        'Referrer-Policy' => 'no-referrer',
    ]);
})->name('formulario');
Route::middleware(['auth', 'can:access-panel'])->group(function () {
    Route::get('/panel/participantes', [ParticipantController::class, 'index'])->name('participants.index');
    Route::post('/panel/participantes', [ParticipantController::class, 'store'])->name('participants.store');
    Route::post('/panel/participantes/importar', [ParticipantController::class, 'import'])->name('participants.import');
    Route::get('/panel/participantes/exportar', [ParticipantController::class, 'export'])->name('participants.export');
    Route::view('/panel', 'panel')->name('dashboard');
    Route::get('/panel/data', [QuestionController::class, 'index']);
    Route::patch('/panel/questions/{question}', [QuestionController::class, 'update']);
    Route::patch('/panel/questions/{question}/restore', [QuestionController::class, 'restore'])->withTrashed();
    Route::delete('/panel/questions/{question}', [QuestionController::class, 'destroy']);
});
require __DIR__.'/auth.php';

if (app()->environment('local')) {
    Route::get('/prueba-formulario', fn () => response()->file(base_path('embed/prueba-local.html')));
}
