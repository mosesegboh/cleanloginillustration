<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <title>{{ config('app.name', 'HFM') }}</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body>
        <div
            id="app"
            data-auth-page="{{ $authPage }}"
            data-csrf-token="{{ csrf_token() }}"
            data-country-options='@json($countryOptions)'
            data-signup-challenge='@json($signupChallenge)'
        ></div>
    </body>
</html>
