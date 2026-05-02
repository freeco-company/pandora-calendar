<?php

use App\Http\Controllers\Api\V1\BodyRhythmController;
use App\Http\Controllers\Api\V1\CycleController;
use App\Http\Controllers\Api\V1\DodoController;
use App\Http\Controllers\Api\V1\SymptomController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'app' => 'pandora-calendar']));

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('/me', fn (Request $r) => response()->json(['data' => [
        'id' => $r->user()->id,
        'name' => $r->user()->name,
    ]]));

    Route::get('/cycles', [CycleController::class, 'index']);
    Route::post('/cycles', [CycleController::class, 'store']);
    Route::delete('/cycles/{cycle}', [CycleController::class, 'destroy']);

    Route::get('/symptoms', [SymptomController::class, 'index']);
    Route::post('/symptoms', [SymptomController::class, 'store']);

    Route::post('/dodo/checkin', [DodoController::class, 'checkin']);
    Route::get('/dodo/recent', [DodoController::class, 'recent']);

    Route::get('/body-rhythm/me', [BodyRhythmController::class, 'me']);
});

// Phase 0 demo: token-less helper to mint a Sanctum token for one of the seeded demo users.
// 上 prod 前移除（受 APP_DEBUG + APP_ENV gate）。
Route::post('/demo/login', function (Request $request) {
    abort_unless(app()->environment('local', 'testing'), 404);
    $request->validate(['email' => ['required', 'email']]);

    $user = \App\Models\User::where('email', $request->input('email'))->first();
    abort_if(! $user, 404, 'demo user not found');

    $token = $user->createToken('demo')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
    ]);
});
