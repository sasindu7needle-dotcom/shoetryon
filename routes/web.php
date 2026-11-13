<?php

use App\Http\Controllers\Admin\AuthenticatedSessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ShoeController;
use App\Http\Controllers\Admin\ShoeFitRoundController;
use App\Http\Controllers\Admin\ShoePositionController;
use App\Http\Controllers\Admin\ShoeSubmissionController;
use App\Http\Controllers\Admin\ShoeSubmissionFileController;
use App\Http\Controllers\Admin\ShoeSubmissionFitRoundController;
use App\Http\Controllers\Admin\ShoeSubmissionLibraryController;
use App\Http\Controllers\Admin\ShoeSubmissionPreviewController;
use App\Http\Controllers\Admin\ShoeSubmissionPublishController;
use App\Http\Controllers\Admin\ShoeSubmissionReviewController;
use App\Http\Controllers\Admin\ShoeVisibilityController;
use App\Http\Controllers\TryOnController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TryOnController::class, 'home'])->name('home');
Route::get('/tryon', [TryOnController::class, 'show'])->name('tryon');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('shoes/new', [ShoeSubmissionController::class, 'create'])->name('shoe-submissions.create');
        Route::post('shoes/new', [ShoeSubmissionController::class, 'store'])->name('shoe-submissions.store');
        Route::get('shoes/new/{submission}', [ShoeSubmissionController::class, 'show'])->name('shoe-submissions.show');
        Route::get('shoes/new/{submission}/files/{file}', [ShoeSubmissionFileController::class, 'show'])->whereIn('file', ['photo', 'model'])->name('shoe-submissions.files.show');
        Route::get('shoes/new/{submission}/preview', [ShoeSubmissionPreviewController::class, 'show'])->name('shoe-submissions.preview');
        Route::put('shoes/new/{submission}/preview', [ShoeSubmissionPreviewController::class, 'update'])->name('shoe-submissions.preview.update');
        Route::get('shoes/new/{submission}/fit-rounds/latest', [ShoeSubmissionFitRoundController::class, 'show'])->name('shoe-submissions.fit-rounds.latest');
        Route::post('shoes/new/{submission}/fit-rounds', [ShoeSubmissionFitRoundController::class, 'store'])->name('shoe-submissions.fit-rounds.store');
        Route::post('shoes/new/{submission}/review', [ShoeSubmissionReviewController::class, 'store'])->name('shoe-submissions.review.store');
        Route::post('shoes/new/{submission}/library', [ShoeSubmissionLibraryController::class, 'store'])->name('shoe-submissions.library.store');
        Route::post('shoes/new/{submission}/publish', [ShoeSubmissionPublishController::class, 'store'])->name('shoe-submissions.publish.store');
        Route::post('shoes/{shoe}/fit-rounds', [ShoeFitRoundController::class, 'store'])->name('shoes.fit-rounds.store');

        Route::resource('shoes', ShoeController::class)->only(['index', 'edit', 'update', 'destroy']);
        Route::patch('shoes/{shoe}/position', [ShoePositionController::class, 'update'])->name('shoes.position.update');
        Route::post('shoes/{shoe}/visibility', [ShoeVisibilityController::class, 'store'])->name('shoes.visibility.store');
        Route::delete('shoes/{shoe}/visibility', [ShoeVisibilityController::class, 'destroy'])->name('shoes.visibility.destroy');
    });
});
