<?php

namespace LaravelPropertyBag\tests\Unit;

use Illuminate\Support\Facades\Cache;
use LaravelPropertyBag\Settings\Settings;
use LaravelPropertyBag\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        config(['propertybag.cache.enabled' => true]);
        config(['propertybag.cache.duration' => 3600]);

        Cache::flush();
    }

    #[Test]
    public function settings_are_cached_when_cache_is_enabled()
    {
        $user = $this->user;
        $user->setSettings(['test_settings1' => 'bananas']);

        $savedKey = $this->savedCacheKey($user);
        $perKeyCacheKey = $this->perKeyCacheKey($user, 'test_settings1');

        $this->assertTrue(Cache::has($savedKey));
        $this->assertEquals('bananas', $user->settings('test_settings1'));
        $this->assertFalse(Cache::has($perKeyCacheKey));
    }

    #[Test]
    public function settings_are_not_cached_when_cache_is_disabled()
    {
        config(['propertybag.cache.enabled' => false]);

        $user = $this->user;
        $user->setSettings(['test_settings1' => 'bananas']);

        $this->assertEquals('bananas', $user->settings('test_settings1'));
        $this->assertFalse(Cache::has($this->savedCacheKey($user)));
        $this->assertFalse(Cache::has($this->allCacheKey($user)));
    }

    #[Test]
    public function all_settings_are_cached()
    {
        $user = $this->user;

        $all1 = $user->allSettings();
        $all2 = $user->allSettings();

        $this->assertEquals($all1, $all2);
        $this->assertTrue(Cache::has($this->allCacheKey($user)));
    }

    #[Test]
    public function cache_is_flushed_when_settings_are_updated()
    {
        $user = $this->user;

        $user->setSettings(['test_settings1' => 'bananas']);
        $user->allSettings();

        $savedKey = $this->savedCacheKey($user);
        $allKey = $this->allCacheKey($user);

        $this->assertTrue(Cache::has($savedKey));
        $this->assertTrue(Cache::has($allKey));

        $user->setSettings(['test_settings1' => 'grapes']);

        $this->assertTrue(Cache::has($savedKey));
        $this->assertFalse(Cache::has($allKey));
        $this->assertEquals('grapes', $user->settings('test_settings1'));
        $this->assertEquals('grapes', $user->allSettings()['test_settings1']);
    }

    #[Test]
    public function stale_per_key_cache_is_ignored()
    {
        $user = $this->user;
        $user->setSettings(['test_settings1' => 'bananas']);

        Cache::put($this->perKeyCacheKey($user, 'test_settings1'), 'stale', 3600);

        $this->assertEquals('bananas', $user->settings('test_settings1'));
    }

    #[Test]
    public function cache_can_be_flushed_for_specific_resource_ids()
    {
        $user1 = $this->user;
        $user2 = $this->makeUser('Another User', 'another@example.com');

        $user1->setSettings(['test_settings1' => 'bananas']);
        $user2->setSettings(['test_settings1' => 'grapes']);

        $user1->allSettings();
        $user2->allSettings();

        Settings::flushCacheForResourceType(get_class($user1), [$user1->id]);

        $this->assertFalse(Cache::has($this->savedCacheKey($user1)));
        $this->assertFalse(Cache::has($this->allCacheKey($user1)));
        $this->assertTrue(Cache::has($this->savedCacheKey($user2)));
        $this->assertTrue(Cache::has($this->allCacheKey($user2)));
    }

    #[Test]
    public function all_cache_can_be_flushed()
    {
        $user = $this->user;
        $post = $this->makePost();

        $user->setSettings(['test_settings1' => 'bananas']);
        $post->setSettings(['test_settings1' => 'grapes']);

        $user->allSettings();
        $post->allSettings();

        $this->assertTrue(Cache::has($this->savedCacheKey($user)));
        $this->assertTrue(Cache::has($this->savedCacheKey($post)));

        Settings::flushAllCache();

        $this->assertFalse(Cache::has($this->savedCacheKey($user)));
        $this->assertFalse(Cache::has($this->allCacheKey($user)));
        $this->assertFalse(Cache::has($this->savedCacheKey($post)));
        $this->assertFalse(Cache::has($this->allCacheKey($post)));
    }

    private function savedCacheKey($resource): string
    {
        return sprintf(
            'property_bag:%s:%s:saved',
            get_class($resource),
            $resource->getKey()
        );
    }

    private function allCacheKey($resource): string
    {
        return sprintf(
            'property_bag:%s:%s:all',
            get_class($resource),
            $resource->getKey()
        );
    }

    private function perKeyCacheKey($resource, string $settingKey): string
    {
        return sprintf(
            'property_bag:%s:%s:%s',
            get_class($resource),
            $resource->getKey(),
            $settingKey
        );
    }
}
