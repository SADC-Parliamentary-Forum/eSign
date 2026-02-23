<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable bot protection for all tests to avoid reCAPTCHA dependency
        Config::set('bot_protection.enabled', false);

        // Flush all cache/rate limiters to prevent 429 errors between tests
        // This ensures each test starts with a clean slate
        try {
            Cache::flush();
        } catch (\Exception $e) {
            // Cache may not be available in all test environments
        }
    }
}
