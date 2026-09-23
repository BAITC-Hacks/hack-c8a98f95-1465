<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['data' => [
    'service' => 'AlemEdu', 'status' => 'ok', 'demoMode' => (bool) config('demo.enabled'),
]]));
Route::get('/tasks', [TaskController::class, 'index']);
Route::get('/tasks/{task}', [TaskController::class, 'show'])->whereNumber('task');
Route::get('/teams', [DemoController::class, 'teams']);
Route::get('/demo/profiles', [DemoController::class, 'profiles']);
Route::get('/demo/briefs', [DemoController::class, 'briefs']);

Route::middleware('demo:customer')->group(function () {
    Route::get('/my/tasks', [TaskController::class, 'mine']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->whereNumber('task');
    Route::post('/tasks/{task}/publish', [TaskController::class, 'publish'])->whereNumber('task');
    Route::post('/ai/questions', [AiController::class, 'questions']);
    Route::get('/tasks/{task}/offers', [OfferController::class, 'index'])->whereNumber('task');
    Route::patch('/offers/{offer}/decision', [OfferController::class, 'decision'])->whereNumber('offer');
});
Route::middleware('demo:team')->group(function () {
    Route::post('/tasks/{task}/offers', [OfferController::class, 'store'])->whereNumber('task');
    Route::get('/my/offers', [OfferController::class, 'mine']);
});
