<?php

use App\Http\Controllers\Api\RecorderAuthController;
use App\Http\Controllers\Api\RecorderTimeTrackingController;
use App\Http\Controllers\Api\RecorderUploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('recorder')->group(function () {
    Route::post('/login', [RecorderAuthController::class, 'login'])->name('api.recorder.login');

    Route::middleware('recorder.token')->group(function () {
        Route::get('/recordings/today', [RecorderUploadController::class, 'todaysRecordings'])->name('api.recorder.recordings.today');
        Route::get('/time-tracking/status', [RecorderTimeTrackingController::class, 'status'])->name('api.recorder.time_tracking.status');
        Route::post('/time-tracking/time-in', [RecorderTimeTrackingController::class, 'timeIn'])->name('api.recorder.time_tracking.time_in');
        Route::post('/time-tracking/time-out', [RecorderTimeTrackingController::class, 'timeOut'])->name('api.recorder.time_tracking.time_out');
        Route::get('/uploads/pending', [RecorderUploadController::class, 'pending'])->name('api.recorder.uploads.pending');
        Route::post('/uploads/start', [RecorderUploadController::class, 'start'])->name('api.recorder.uploads.start');
        Route::post('/uploads/chunk', [RecorderUploadController::class, 'upload'])->name('api.recorder.uploads.chunk');
        Route::post('/uploads/finalize', [RecorderUploadController::class, 'finalize'])->name('api.recorder.uploads.finalize');
        Route::post('/uploads/status', [RecorderUploadController::class, 'updateStatus'])->name('api.recorder.uploads.status');
    });
});
