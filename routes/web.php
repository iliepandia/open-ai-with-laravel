<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use OpenAI\Laravel\Facades\OpenAI;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register') && config('app.allow_registration'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    $messages = session()->get("messages", []);
    $userId = \request()->user()->id;
    \Log::debug("Conversation so far for user: {$userId}", $messages);
    return Inertia::render('Dashboard', [
        'messages' => $messages,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {

    Route::prefix('api')->middleware(['throttle:ai-call'])->group(function () {
        Route::post('ask-ai', [\App\Http\Controllers\OpenAiApiController::class, 'askAi']);
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


require __DIR__.'/auth.php';
