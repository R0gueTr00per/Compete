<?php

use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/portal');
});

Route::get('/schedule/{competition}', [PublicScheduleController::class, 'show'])->name('public.schedule');

// Social OAuth routes
Route::get('auth/{provider}/redirect', [SocialiteController::class, 'redirect'])->name('socialite.redirect');
Route::middleware('throttle:10,1')->get('auth/{provider}/callback', [SocialiteController::class, 'callback'])->name('socialite.callback');

// Profile completion alias — named route used by RequireCompleteProfile middleware and SocialiteController
Route::middleware(['auth'])->get('/portal/profile-setup', fn () => redirect()->route('filament.portal.pages.profile'))->name('profile.complete');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
