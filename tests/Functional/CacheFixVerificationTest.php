<?php

namespace LaravelPropertyBag\tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Cache;
use LaravelPropertyBag\tests\TestCase;

/**
 * This test verifies that the cache synchronization issue is fixed.
 * Previously, changing an individual setting would invalidate the individual
 * cache key but not the "all settings" cache, causing allSettings() to
 * return stale data.
 */
class CacheFixVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        config(['cache.default' => 'array']);
        config(['propertybag.cache.enabled' => true]);
        config(['propertybag.cache.duration' => 3600]);
        
        Cache::flush();
    }

    /**
     * Verifies the specific bug is fixed: changing individual settings
     * now properly invalidates the allSettings() cache.
     */
    #[Test]
    public function changing_individual_setting_invalidates_all_settings_cache()
    {
        $user = $this->user;
        
        // Step 1: Set initial values
        $user->setSettings([
            'test_settings1' => 'bananas',
            'test_settings2' => true,
        ]);
        
        // Step 2: Cache both individual and all settings
        $this->assertEquals('bananas', $user->settings('test_settings1'));
        $all = $user->allSettings();
        $this->assertEquals('bananas', $all['test_settings1']);
        $this->assertEquals(true, $all['test_settings2']);
        
        // Verify both caches exist
        $individualCacheKey = "property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:test_settings1";
        $allCacheKey = "property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:all";
        $this->assertTrue(Cache::has($individualCacheKey), "Individual cache should exist");
        $this->assertTrue(Cache::has($allCacheKey), "All settings cache should exist");
        
        // Step 3: Change individual setting using settings() method
        $user->settings(['test_settings1' => 'grapes']);
        
        // Step 4: Verify the all cache was invalidated (the fix)
        $this->assertFalse(Cache::has($allCacheKey), "All settings cache should be invalidated after individual change");
        
        // Step 5: Verify allSettings() returns the new value
        $allAfterChange = $user->allSettings();
        $this->assertEquals('grapes', $allAfterChange['test_settings1'], "allSettings() must return the updated value");
        $this->assertEquals(true, $allAfterChange['test_settings2'], "Other settings should remain unchanged");
    }

    /**
     * Test that setSettings() also properly invalidates all caches
     */
    #[Test]
    public function set_settings_invalidates_all_relevant_caches()
    {
        $user = $this->user;
        
        // Set and cache initial values
        $user->setSettings(['test_settings1' => 'bananas']);
        $user->settings('test_settings1');
        $user->allSettings();
        
        // Verify caches exist
        $individualCacheKey = "property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:test_settings1";
        $allCacheKey = "property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:all";
        $this->assertTrue(Cache::has($individualCacheKey));
        $this->assertTrue(Cache::has($allCacheKey));
        
        // Change via setSettings
        $user->setSettings(['test_settings1' => 'grapes']);
        
        // Both caches should be invalidated
        $this->assertFalse(Cache::has($allCacheKey), "All cache should be cleared");
        
        // Values should be updated
        $this->assertEquals('grapes', $user->settings('test_settings1'));
        $this->assertEquals('grapes', $user->allSettings()['test_settings1']);
    }
}