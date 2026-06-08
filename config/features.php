<?php

return [
    'signup_enabled' => env('FEATURE_SIGNUP_ENABLED', true),
    'login_enabled' => env('FEATURE_LOGIN_ENABLED', true),
    'signup_challenge_minimum_seconds' => env('SIGNUP_CHALLENGE_MINIMUM_SECONDS', 2),
    'signup_challenge_lifetime_seconds' => env('SIGNUP_CHALLENGE_LIFETIME_SECONDS', 7200),
];
