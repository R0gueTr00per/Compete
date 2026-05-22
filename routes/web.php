<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicScheduleController;
use App\Http\Controllers\ResultsPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // On an org subdomain → redirect to portal
    if (app('tenant')) {
        return redirect('/portal');
    }
    // Root domain → landing page with org search
    $query = request()->query('q');
    $orgs  = $query
        ? \App\Models\Organisation::active()->where('name', 'like', '%' . $query . '%')->get()
        : collect();
    return view('landing', compact('orgs', 'query'));
})->name('landing');

Route::get('/schedule/{competition}', [PublicScheduleController::class, 'show'])->name('public.schedule');

// Org admin invitation magic links
Route::get('/invite/org-admin/{membership}', [InvitationController::class, 'accept'])
    ->name('invite.org-admin.accept')
    ->middleware('signed');
Route::post('/invite/org-admin/{membership}', [InvitationController::class, 'complete'])
    ->name('invite.org-admin.complete');

// Admin panel has no login page — redirect any /admin/login requests to portal
Route::get('admin/login', fn () => redirect()->route('filament.portal.auth.login'))
    ->name('filament.admin.auth.login');

// Social OAuth routes (disabled — re-enable when Google login is restored)
// Route::get('auth/{provider}/redirect', [SocialiteController::class, 'redirect'])->name('socialite.redirect');
// Route::middleware('throttle:10,1')->get('auth/{provider}/callback', [SocialiteController::class, 'callback'])->name('socialite.callback');

// Profile completion alias — named route used by RequireCompleteProfile middleware and SocialiteController
Route::middleware(['auth'])->get('/portal/profile-setup', fn () => redirect()->route('filament.portal.pages.profile'))->name('profile.complete');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/admin/results/pdf', [ResultsPdfController::class, 'show'])->name('results.pdf');
});

// Unauthenticated email verification for pending portal users
Route::get('/portal/verify-email/{id}/{hash}', \App\Http\Controllers\Auth\PortalVerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('portal.verify-email');

Route::get('/portal/email-verified', fn () => view('portal.email-verified'))->name('portal.email-verified');

require __DIR__.'/auth.php';
