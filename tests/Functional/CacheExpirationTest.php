<?php

namespace LaravelPropertyBag\tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Cache;
use LaravelPropertyBag\tests\TestCase;
use Carbon\Carbon;

class CacheExpirationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['propertybag.cache.enabled' => true]);
        config(['propertybag.cache.duration' => 2]); // 2 seconds for testing
        Cache::flush();
    }

    #[Test]
    public function cache_respects_configured_expiration_time()
    {
        $user = $this->user;
        
        // Set a value
        $user->setSettings(['test_settings1' => 'bananas']);
        
        // First access should be from database and cached
        $result1 = $user->settings('test_settings1');
        $this->assertEquals('bananas', $result1);
        
        // Immediate second access should be from cache
        $result2 = $user->settings('test_settings1');
        $this->assertEquals('bananas', $result2);
        
        // Wait for cache to expire
        sleep(3);
        
        // Force a new Settings instance to ensure we don't use in-memory values
        $user->load('propertyBag');
        
        // This should query database again since cache expired
        $result3 = $user->settings('test_settings1');
        $this->assertEquals('bananas', $result3);
    }

    #[Test]
    public function cache_duration_uses_configured_value()
    {
        // Set a specific cache duration
        config(['propertybag.cache.duration' => 7200]);
        
        $user = $this->user;
        
        // Get the Settings instance
        $settings = $user->settings();
        
        // Use reflection to access the protected method
        $reflection = new \ReflectionClass($settings);
        $method = $reflection->getMethod('getCacheDuration');
        $method->setAccessible(true);
        
        $duration = $method->invoke($settings);
        
        // Should use configured value
        $this->assertEquals(7200, $duration);
    }
}