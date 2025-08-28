<?php

namespace LaravelPropertyBag\tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Cache;
use LaravelPropertyBag\tests\TestCase;

class CacheSyncIssueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Use array cache driver for testing
        config(['cache.default' => 'array']);
        config(['propertybag.cache.enabled' => true]);
        config(['propertybag.cache.duration' => 3600]);
        
        // Clear any existing cache
        Cache::flush();
    }

    /**
     * This test reproduces the issue where individual key cache is invalidated
     * but allSettings() still returns old values.
     */
    #[Test]
    public function all_settings_cache_is_invalidated_when_individual_setting_changes()
    {
        $user = $this->user;
        
        // Set initial value
        $user->setSettings(['test_settings1' => 'bananas']);
        
        // Access allSettings() to cache it
        $allBefore = $user->allSettings();
        $this->assertEquals('bananas', $allBefore['test_settings1']);
        
        // Verify the all settings cache exists
        $allCacheKey = "property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:all";
        $this->assertTrue(Cache::has($allCacheKey), "All settings cache should exist");
        
        // Change the individual setting
        $user->settings(['test_settings1' => 'grapes']);
        
        // Access the individual setting - should get new value
        $individualValue = $user->settings('test_settings1');
        $this->assertEquals('grapes', $individualValue);
        
        // Now access allSettings() - should also get the new value
        // BUG: This is where the issue occurs - allSettings() returns old cached value
        $allAfter = $user->allSettings();
        $this->assertEquals('grapes', $allAfter['test_settings1'], "allSettings() should return the updated value");
    }
    
    /**
     * Test that both individual and all caches are properly synchronized
     */
    #[Test]
    public function cache_synchronization_between_individual_and_all_settings()
    {
        $user = $this->user;
        
        // Set multiple values
        $user->setSettings([
            'test_settings1' => 'bananas',
            'test_settings2' => true,
        ]);
        
        // Cache both individual and all settings
        $user->settings('test_settings1');
        $user->settings('test_settings2');
        $user->allSettings();
        
        // Change one setting via individual key
        $user->settings(['test_settings1' => 'grapes']);
        
        // Both individual and all should reflect the change
        $this->assertEquals('grapes', $user->settings('test_settings1'));
        $this->assertEquals(true, $user->settings('test_settings2'));
        
        $all = $user->allSettings();
        $this->assertEquals('grapes', $all['test_settings1']);
        $this->assertEquals(true, $all['test_settings2']);
    }
}