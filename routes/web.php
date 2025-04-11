<?php

use App\Http\Controllers\ProfileController;
use App\Models\Conversation;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register') && config('app.allow_registration'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});


Route::get('/dashboard', function () {
    $userId = \request()->user()->id;
    $threadId = session()->get('threadId', 'not-set');
    $messages = Conversation::where('thread_id', $threadId)
        ->where('user_id', $userId)->orderBy('id')->get()->toArray();
    $messages = array_map(function($message){
        unset($message['assistant_id']);
        unset($message['run_id']);
        if($message['annotations']){
            $message['annotations'] = json_decode($message['annotations']);
        }
        return $message;
    }, $messages);
    return Inertia::render('Dashboard', [
        'messages' => $messages,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {

    Route::get('/tests', [\App\Http\Controllers\OpenAiApiController::class, 'tests'])->name('tests');

    Route::prefix('api')->middleware(['throttle:ai-call'])->group(function () {
        Route::post('ask-ai', [\App\Http\Controllers\OpenAiApiController::class, 'askAi']);
        Route::post('vote-message', [\App\Http\Controllers\OpenAiApiController::class, 'voteMessage']);
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


require __DIR__.'/auth.php';
