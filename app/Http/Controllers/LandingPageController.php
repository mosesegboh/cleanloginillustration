<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\CountryMetadataProvider;
use App\Support\Security\SignupSpamChallenge;
use Illuminate\Contracts\View\View;

/**
 * Renders the React auth shell with server-provided country metadata.
 */
final class LandingPageController extends Controller
{
    /**
     * Create the controller with the country metadata provider used by registration.
     */
    public function __construct(
        private readonly CountryMetadataProvider $countryMetadataProvider,
        private readonly SignupSpamChallenge $signupSpamChallenge,
    ) {}

    /**
     * Render the registration page.
     */
    public function register(): View
    {
        return $this->renderAuthPage('register');
    }

    /**
     * Render the login page.
     */
    public function login(): View
    {
        return $this->renderAuthPage('login');
    }

    /**
     * Render the shared auth shell for a specific auth page.
     */
    private function renderAuthPage(string $authPage): View
    {
        return view('app', [
            'authPage' => $authPage,
            'countryOptions' => $this->countryMetadataProvider->toFrontendOptions(),
            'signupChallenge' => $authPage === 'register' ? $this->signupSpamChallenge->issue() : null,
        ]);
    }
}
