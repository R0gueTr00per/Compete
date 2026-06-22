<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PublicScheduleController;
use App\Http\Controllers\ResultsPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // On an org subdomain → redirect to portal
    if (app('tenant')) {
        return redirect('/portal');
    }
    return view('landing');
})->name('landing');

Route::get('/orgs/search', function () {
    $query = trim(request()->query('q', ''));
    if (strlen($query) < 2) {
        return response()->json([]);
    }
    $orgs = \App\Models\Organisation::active()
        ->where('name', 'like', '%' . $query . '%')
        ->get(['name', 'slug'])
        ->map(fn ($org) => [
            'name' => $org->name,
            'slug' => $org->slug,
            'url'  => config('app.scheme') . '://' . $org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal',
        ]);
    return response()->json($orgs);
})->name('orgs.search');

Route::get('/schedule/{competition}', [PublicScheduleController::class, 'show'])->name('public.schedule');

Route::middleware(\App\Http\Middleware\ResolveTenant::class)->group(function () {
    Route::get('/organisation-disabled', fn () => view('organisation-disabled'))->name('organisation.disabled');
    Route::get('/competitor-access-disabled', fn () => view('competitor-access-disabled'))->name('competitor.access.disabled');
});

// Org admin invitation magic links
Route::get('/invite/org-admin/{membership}', [InvitationController::class, 'accept'])
    ->name('invite.org-admin.accept')
    ->middleware('signed');
Route::post('/invite/org-admin/{membership}', [InvitationController::class, 'complete'])
    ->name('invite.org-admin.complete');

// Admin panel has no login page — redirect any /admin/login requests to portal
Route::get('admin/login', fn () => redirect()->route('filament.portal.auth.login'))
    ->name('filament.admin.auth.login');

// Profile completion alias — named route used by RequireCompleteProfile middleware
Route::middleware(['auth'])->get('/portal/profile-setup', fn () => redirect()->route('filament.portal.pages.profile'))->name('profile.complete');

Route::middleware('auth')->group(function () {
    Route::get('/admin/results/pdf', [ResultsPdfController::class, 'show'])->name('results.pdf');
    Route::get('/admin/results/pdf/medal-tally-competitor', [ResultsPdfController::class, 'medalTallyCompetitor'])->name('results.pdf.medal-tally-competitor');
    Route::get('/admin/results/pdf/medal-tally-dojo', [ResultsPdfController::class, 'medalTallyDojo'])->name('results.pdf.medal-tally-dojo');
});

// Unauthenticated email verification for pending portal users
Route::get('/portal/verify-email/{id}/{hash}', \App\Http\Controllers\Auth\PortalVerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('portal.verify-email');

// Email change verification — confirms a pending_email update
Route::get('/portal/verify-email-change/{id}/{hash}', \App\Http\Controllers\Auth\EmailChangeVerificationController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('portal.verify-email-change');

Route::get('/portal/email-verified', fn () => view('portal.email-verified'))->name('portal.email-verified');

require __DIR__.'/auth.php';
