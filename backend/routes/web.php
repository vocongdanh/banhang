<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;

Route::get('/', function () {
    return view('welcome');
});

// OAuth routes cho môi trường test/production
Route::get('/oauth/facebook', [RegisterController::class, 'redirectToFacebook'])->name('oauth.facebook');
Route::get('/oauth/google', [RegisterController::class, 'redirectToGoogle'])->name('oauth.google');

// OAuth callbacks
Route::get('/oauth/facebook/callback', [RegisterController::class, 'handleFacebookCallback'])->name('oauth.facebook.callback');
Route::get('/oauth/google/callback', [RegisterController::class, 'handleGoogleCallback'])->name('oauth.google.callback');
