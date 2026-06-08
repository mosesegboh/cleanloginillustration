<?php

use App\Enums\FeatureFlag;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\SignupController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/register')->name('landing');
Route::get('/register', [LandingPageController::class, 'register'])->name('register');
Route::post('/register', SignupController::class)
    ->middleware(['feature:'.FeatureFlag::Signup->value, 'throttle:signup'])
    ->name('register.store');
Route::get('/login', [LandingPageController::class, 'login'])->name('login');
Route::post('/login', LoginController::class)
    ->middleware(['feature:'.FeatureFlag::Login->value, 'throttle:login'])
    ->name('login.validate');
