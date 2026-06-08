<?php

use App\Enums\FeatureFlag;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\SignupChallengeController;
use App\Http\Controllers\SignupController;
use Illuminate\Support\Facades\Route;

Route::get('/signup-challenge', SignupChallengeController::class)
    ->middleware(['feature:'.FeatureFlag::Signup->value, 'throttle:signup-challenge'])
    ->name('api.signups.challenge');

Route::post('/signups', SignupController::class)
    ->middleware(['feature:'.FeatureFlag::Signup->value, 'throttle:signup'])
    ->name('api.signups.store');

Route::post('/login', LoginController::class)
    ->middleware(['feature:'.FeatureFlag::Login->value, 'throttle:login'])
    ->name('api.login');
