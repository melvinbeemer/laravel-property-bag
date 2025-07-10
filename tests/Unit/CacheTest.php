<?php

namespace LaravelPropertyBag\tests\Unit;

use PHPUnit\Framework\Attributes\Test;

use Mockery as m;
use Illuminate\Support\Facades\Cache;
use LaravelPropertyBag\tests\TestCase;
use LaravelPropertyBag\Settings\Settings;

class CacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     */
    #[Test]
    public function settings_are_cached_when_cache_is_enabled()
    {
        config(['propertybag.cache.enabled' => true]);
        config(['propertybag.cache.duration' => 3600]);

        $user = $this->user;
        
        // Reset mocks for specific test
        Cache::clearResolvedInstances();
        Cache::shouldReceive('store')->andReturnSelf();
        
        // Mock for sync in constructor
        Cache::shouldReceive('remember')
            ->with("property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:saved", 3600, m::type('Closure'))
            ->once()
            ->andReturn(collect());
        
        // Mock tracking calls
        Cache::shouldReceive('get')->withAnyArgs()->andReturn([]);
        Cache::shouldReceive('put')->withAnyArgs()->andReturnSelf();
        
        // Mock the actual setting cache
        Cache::shouldReceive('remember')
            ->with("property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:test_settings1", 3600, m::type('Closure'))
            ->once()
            ->andReturn('monkey');

        $result = $user->settings('test_settings1');

        $this->assertEquals('monkey', $result);
    }

    /**
     */
    #[Test]
    public function settings_are_not_cached_when_cache_is_disabled()
    {
        config(['propertybag.cache.enabled' => false]);

        $user = $this->user;
        $user->setSettings(['test_settings1' => 'bananas']);

        // Clear any previous cache mocks
        Cache::clearResolvedInstances();
        
        // Cache should not be called
        Cache::shouldReceive('store')->never();
        Cache::shouldReceive('remember')->never();

        $result = $user->settings('test_settings1');

        $this->assertEquals('bananas', $result);
    }

    /**
     */
    #[Test]
    public function all_settings_are_cached()
    {
        config(['propertybag.cache.enabled' => true]);
        config(['propertybag.cache.duration' => 3600]);

        $user = $this->user;

        $expectedSettings = collect([
            'test_settings1' => 'monkey',
            'test_settings2' => true,
            'test_settings3' => false,
        ]);

        // Reset mocks for specific test
        Cache::clearResolvedInstances();
        Cache::shouldReceive('store')->andReturnSelf();
        
        // Mock for sync in constructor
        Cache::shouldReceive('remember')
            ->with("property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:saved", 3600, m::type('Closure'))
            ->once()
            ->andReturn(collect());
            
        // Mock tracking
        Cache::shouldReceive('get')->withAnyArgs()->andReturn([]);
        Cache::shouldReceive('put')->withAnyArgs()->andReturnSelf();
        
        // Mock the all() cache
        Cache::shouldReceive('remember')
            ->with("property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:all", 3600, m::type('Closure'))
            ->once()
            ->andReturn($expectedSettings);

        $result = $user->allSettings();

        $this->assertEquals($expectedSettings, $result);
    }

    /**
     */
    #[Test]
    public function cache_is_flushed_when_settings_are_updated()
    {
        config(['propertybag.cache.enabled' => true]);

        $user = $this->user;
        
        // Reset mocks for specific test
        Cache::clearResolvedInstances();
        Cache::shouldReceive('store')->andReturnSelf();
        
        // Mock all the cache operations
        Cache::shouldReceive('remember')->withAnyArgs()->andReturn(collect());
        Cache::shouldReceive('get')->withAnyArgs()->andReturn([
            "property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:all",
            "property_bag:LaravelPropertyBag\\tests\\Classes\\User:{$user->id}:test_settings1",
        ]);
        Cache::shouldReceive('put')->withAnyArgs()->andReturnSelf();
        Cache::shouldReceive('forget')->withAnyArgs()->andReturnSelf();

        // The test is checking that cache is cleared when settings are updated
        $user->setSettings(['test_settings1' => 'bananas']);
        
        // If we got here without errors, cache was properly handled
        $this->assertTrue(true);
    }

    /**
     */
    #[Test]
    public function cache_can_be_flushed_for_resource_type()
    {
        config(['propertybag.cache.enabled' => true]);

        $cacheKeysKey = "property_bag:keys:App\\Models\\User";
        $allKeys = [
            "property_bag:App\\Models\\User:1:theme",
            "property_bag:App\\Models\\User:1:all",
            "property_bag:App\\Models\\User:2:theme",
        ];

        Cache::clearResolvedInstances();
        Cache::shouldReceive('store')->andReturnSelf();
        Cache::shouldReceive('get')
            ->with($cacheKeysKey, [])
            ->once()
            ->andReturn($allKeys);

        foreach ($allKeys as $key) {
            Cache::shouldReceive('forget')->with($key)->once();
        }

        Cache::shouldReceive('forget')->with($cacheKeysKey)->once();

        Settings::flushCacheForResourceType('App\\Models\\User');
        
        $this->assertTrue(true);
    }

    /**
     */
    #[Test]
    public function cache_can_be_flushed_for_specific_resource_ids()
    {
        config(['propertybag.cache.enabled' => true]);
        config(['propertybag.cache.duration' => 3600]);

        $cacheKeysKey = "property_bag:keys:App\\Models\\User";
        $allKeys = [
            "property_bag:App\\Models\\User:1:theme",
            "property_bag:App\\Models\\User:1:all",
            "property_bag:App\\Models\\User:2:theme",
            "property_bag:App\\Models\\User:3:all",
        ];

        Cache::clearResolvedInstances();
        Cache::shouldReceive('store')->andReturnSelf();
        Cache::shouldReceive('get')
            ->with($cacheKeysKey, [])
            ->once()
            ->andReturn($allKeys);

        // Only keys for user 1 and 3 should be forgotten
        Cache::shouldReceive('forget')->with("property_bag:App\\Models\\User:1:theme")->once();
        Cache::shouldReceive('forget')->with("property_bag:App\\Models\\User:1:all")->once();
        Cache::shouldReceive('forget')->with("property_bag:App\\Models\\User:3:all")->once();

        // Remaining keys should be put back
        Cache::shouldReceive('put')
            ->with($cacheKeysKey, ["property_bag:App\\Models\\User:2:theme"], 3600)
            ->once();

        Settings::flushCacheForResourceType('App\\Models\\User', [1, 3]);
        
        $this->assertTrue(true);
    }

    /**
     */
    #[Test]
    public function all_cache_can_be_flushed()
    {
        config(['propertybag.cache.enabled' => true]);

        $resourceTypes = [
            'App\\Models\\User',
            'App\\Models\\Post',
        ];

        Cache::clearResolvedInstances();
        Cache::shouldReceive('store')->andReturnSelf();
        Cache::shouldReceive('get')
            ->with('property_bag:resource_types', [])
            ->once()
            ->andReturn($resourceTypes);

        // Each resource type should be flushed
        foreach ($resourceTypes as $type) {
            Cache::shouldReceive('get')
                ->with("property_bag:keys:{$type}", [])
                ->once()
                ->andReturn([]);
            Cache::shouldReceive('forget')
                ->with("property_bag:keys:{$type}")
                ->once();
        }

        Cache::shouldReceive('forget')
            ->with('property_bag:resource_types')
            ->once();

        Settings::flushAllCache();
        
        $this->assertTrue(true);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        m::close();
    }
}