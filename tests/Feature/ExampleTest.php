<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the top-level browser routes for the auth pages.
 */
class ExampleTest extends TestCase
{
    /**
     * Assert the register page renders successfully.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    /**
     * Assert the React app receives a CSRF token for browser form submissions.
     */
    public function test_register_page_exposes_csrf_token_for_react_forms(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSee('data-csrf-token=', false);
    }

    /**
     * Assert the login page renders successfully.
     */
    public function test_login_page_returns_a_successful_response(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    /**
     * Assert the landing route redirects users to registration.
     */
    public function test_landing_page_redirects_to_register(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/register');
    }
}
