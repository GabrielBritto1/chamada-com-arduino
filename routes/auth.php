<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
   Volt::route('register', 'pages.auth.register')
      ->name('register');

   Volt::route('login', 'pages.auth.login')
      ->name('login');

   Volt::route('forgot-password', 'pages.auth.forgot-password')
      ->name('password.request');

   Volt::route('reset-password/{token}', 'pages.auth.reset-password')
      ->name('password.reset');
});

Route::middleware('auth')->group(function () {
   Volt::route('verify-email', 'pages.auth.verify-email')
      ->name('verification.notice');

   Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
      ->middleware(['signed', 'throttle:6,1'])
      ->name('verification.verify');

   Volt::route('confirm-password', 'pages.auth.confirm-password')
      ->name('password.confirm');
});


// ROTAS DE AUTENTICAÇÃO
Route::get('/auth/github/redirect', [LoginController::class, 'gitHubRedirect'])
   ->name('auth.github.redirect');
Route::get('/auth/github/callback', [LoginController::class, 'gitHubCallback'])
   ->name('auth.github.callback');

Route::get('/auth/google/redirect', [LoginController::class, 'googleRedirect'])
   ->name('auth.google.redirect');
Route::get('/auth/google/callback', [LoginController::class, 'googleCallback'])
   ->name('auth.google.callback');
