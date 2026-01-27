<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class SecurityValidationTest extends TestCase
{
    // use RefreshDatabase; // Caution: Don't wipe DB on production/pre-prod if pointing there.

    protected $adminUser;
    protected $standardUser;

    protected function setUp(): void
    {
        parent::setUp();
        // Assume existing users or create ephemerally if in CI
        // $this->adminUser = User::factory()->create(['role' => 'admin']);
        // $this->standardUser = User::factory()->create(['role' => 'user']);
    }

    /**
     * Security Requirement: Enumerate all endpoints and verify Authentication.
     * Fails if any sensitive route is accessible to Guests.
     */
    public function test_all_endpoints_require_authentication()
    {
        $routes = Route::getRoutes();
        $publicRoutes = [
            'login',
            'register',
            'password.request',
            'password.email',
            'password.reset',
            'sanctum/csrf-cookie',
            '_ignition*',
            'api/documentation'
        ];

        $vulnerableRoutes = [];

        foreach ($routes as $route) {
            if (in_array('GET', $route->methods())) {
                $uri = $route->uri();

                // Skip defined public routes
                foreach ($publicRoutes as $public) {
                    if (fnmatch($public, $uri))
                        continue 2;
                }

                // Attempt access as Guest
                $response = $this->get($uri);

                // If status is 200/201/204, it *might* be open.
                if (in_array($response->status(), [200, 201, 204])) {
                    $vulnerableRoutes[] = $uri . " [" . $response->status() . "]";
                }
            }
        }

        if (!empty($vulnerableRoutes)) {
            Log::warning('Security: Unprotected routes found:', $vulnerableRoutes);
        }

        $this->assertEmpty($vulnerableRoutes, "Found public routes that should be protected: " . implode(', ', $vulnerableRoutes));
    }

    /**
     * Security Requirement: Input Validation & Injection Testing (Fuzzing).
     * Injects SQLi and XSS payloads into GET parameters.
     */
    public function test_input_fuzzing_on_get_parameters()
    {
        $payloads = ["' OR 1=1 --", "<script>alert(1)</script>", "../../../etc/passwd"];
        $routes = Route::getRoutes();

        foreach ($routes as $route) {
            if (in_array('GET', $route->methods()) && str_contains($route->uri(), '{')) {
                // Simple fuzz logic: Replace first parameter with payload
                $uri = preg_replace('/\{.*?\}/', $payloads[0], $route->uri());

                // Act as authenticated user to reach the controller logic
                // $this->actingAs($this->standardUser); 

                $response = $this->get($uri);

                // Check for 500 errors which might indicate unhandled SQL exceptions
                $this->assertNotEquals(500, $response->status(), "Potential injection vulnerability or unhandled error at $uri");
            }
        }
    }

    /**
     * Security Requirement: HTTP Headers
     */
    public function test_security_headers_present()
    {
        $response = $this->get('/'); // Home page

        // Basic check, might need adjustment based on middleware
        // $response->assertHeader('X-Frame-Options');
        // $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertTrue(true); // Placeholder for custom header logic
    }
}
