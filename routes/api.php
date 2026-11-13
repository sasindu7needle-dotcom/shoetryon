<?php

use App\Http\Controllers\N8n\ShoeFitRoundCallbackController;
use App\Http\Controllers\N8n\ShoeSubmissionCallbackController;
use Illuminate\Support\Facades\Route;

/*
| Callbacks from the n8n "ShopAR: admin shoe pipeline" workflow. Every call
| carries the shared key from N8N_SHOPAR_KEY in the X-ShopAR-Key header.
*/
Route::prefix('n8n')->name('n8n.')->middleware('n8n.key')->group(function () {
    Route::post('shoe-submissions/{slug}/model', [ShoeSubmissionCallbackController::class, 'storeModel'])->name('shoe-submissions.model');
    Route::post('shoe-submissions/{slug}/failure', [ShoeSubmissionCallbackController::class, 'storeFailure'])->name('shoe-submissions.failure');

    Route::post('fit-rounds/{round}/result', [ShoeFitRoundCallbackController::class, 'storeResult'])->name('fit-rounds.result');
    Route::post('fit-rounds/{round}/failure', [ShoeFitRoundCallbackController::class, 'storeFailure'])->name('fit-rounds.failure');
});
