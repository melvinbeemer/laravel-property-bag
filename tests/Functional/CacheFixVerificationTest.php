<?php

namespace LaravelPropertyBag\tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Cache;
use LaravelPropertyBag\tests\TestCase;

/**
 * This test verifies that the cache synchronization issue is fixed.
 * Previously, changing an individual setting could leave stale key-level cache
 * entries around and cause allSettings() to
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
     * Verifies changing individual settings invalidates allSettings() cache
     * while reads continue to use the saved snapshot cache.
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
        
        // Step 2: Cache settings
        $this->assertEquals('bananas', $user->settings('test_settings1'));
        $all = $user->allSettings();
        $this->assertEquals('bananas', $all['test_settings1']);
        $this->assertEquals(true, $all['test_settings2']);

        // Verify cache shape
        $savedCacheKey = "property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:saved";
        $allCacheKey = "property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:all";
        $legacyPerKeyCacheKey = "property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:test_settings1";
        $this->assertTrue(Cache::has($savedCacheKey), "Saved settings cache should exist");
        $this->assertTrue(Cache::has($allCacheKey), "All settings cache should exist");
        $this->assertFalse(Cache::has($legacyPerKeyCacheKey), "Per-key cache should not be used");
        
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
     * Test that setSettings() properly invalidates and rebuilds deterministic caches.
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
        $savedCacheKey = "property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:saved";
        $allCacheKey = "property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:all";
        $legacyPerKeyCacheKey = "property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:test_settings1";
        $this->assertTrue(Cache::has($savedCacheKey));
        $this->assertTrue(Cache::has($allCacheKey));
        $this->assertFalse(Cache::has($legacyPerKeyCacheKey));
        
        // Change via setSettings
        $user->setSettings(['test_settings1' => 'grapes']);
        
        // allSettings cache should be invalidated, saved cache repopulated during sync
        $this->assertFalse(Cache::has($allCacheKey), "All cache should be cleared");
        $this->assertTrue(Cache::has($savedCacheKey), "Saved cache should be rebuilt");
        
        // Values should be updated
        $this->assertEquals('grapes', $user->settings('test_settings1'));
        $this->assertEquals('grapes', $user->allSettings()['test_settings1']);
    }
}
