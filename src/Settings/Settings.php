<?php

namespace LaravelPropertyBag\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use LaravelPropertyBag\Settings\Rules\RuleValidator;
use LaravelPropertyBag\Exceptions\InvalidSettingsValue;

class Settings
{
    /**
     * Settings for resource.
     *
     * @var \LaravelPropertyBag\Settings\ResourceConfig
     */
    protected $settingsConfig;

    /**
     * Resource that has settings.
     *
     * @var Model
     */
    protected $resource;

    /**
     * Registered keys, values, and defaults.
     * 'key' => ['allowed' => $value, 'default' => $value].
     *
     * @var \Illuminate\Support\Collection
     */
    protected $registered;

    /**
     * Settings saved in database. Does not include defaults.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $settings;

    /**
     * Validator for allowed rules.
     *
     * @var \LaravelPropertyBag\Settings\Rules\RuleValidator
     */
    protected $ruleValidator;

    /**
     * Cache store instance.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * Construct.
     *
     * @param ResourceConfig $settingsConfig
     * @param Model          $resource
     */
    public function __construct(ResourceConfig $settingsConfig, Model $resource)
    {
        $this->settingsConfig = $settingsConfig;
        $this->resource = $resource;

        $this->ruleValidator = new RuleValidator();
        $this->registered = $settingsConfig->registeredSettings();
        $this->cache = $this->getCacheStore();

        $this->sync();
    }

    /**
     * Get the property bag relationshp off the resource.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    protected function propertyBag()
    {
        return $this->resource->propertyBag();
    }

    /**
     * Get resource config.
     *
     * @return \LaravelPropertyBag\Settings\ResourceConfig
     */
    public function getResourceConfig()
    {
        return $this->settingsConfig;
    }

    /**
     * Get registered settings.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getRegistered()
    {
        return $this->registered;
    }

    /**
     * Return true if key exists in registered settings collection.
     *
     * @param string $key
     *
     * @return bool
     */
    public function isRegistered($key)
    {
        return $this->getRegistered()->has($key);
    }

    /**
     * Return true if key and value are registered values.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    public function isValid($key, $value)
    {
        $settings = collect(
            $this->getRegistered()->get($key, ['allowed' => []])
        );

        $allowed = $settings->get('allowed');

        if (!is_array($allowed) &&
            $rule = $this->ruleValidator->isRule($allowed)) {
            return $this->ruleValidator->validate($rule, $value);
        }

        return in_array($value, $allowed, true);
    }

    /**
     * Return true if value is default value for key.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    public function isDefault($key, $value)
    {
        return $this->getDefault($key) === $value;
    }

    /**
     * Get the default value from registered.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getDefault($key)
    {
        if ($this->isRegistered($key)) {
            return $this->getRegistered()[$key]['default'];
        }
    }

    /**
     * Return all settings used by resource, including defaults.
     *
     * @return \Illuminate\Support\Collection
     */
    public function all()
    {
        if (!$this->isCacheEnabled()) {
            return $this->getAllFromDatabase();
        }

        $cacheKey = $this->getAllCacheKey();
        
        return $this->cacheRemember($cacheKey, function () {
            return $this->getAllFromDatabase();
        });
    }

    /**
     * Get all settings from database.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getAllFromDatabase()
    {
        $saved = $this->allSaved();

        return $this->allDefaults()->map(function ($value, $key) use ($saved) {
            if ($saved->has($key)) {
                return $saved->get($key);
            }

            return $value;
        });
    }

    /**
     * Get all defaults for settings.
     *
     * @return \Illuminate\Support\Collection
     */
    public function allDefaults()
    {
        return $this->getRegistered()->map(function ($value) {
            return $value['default'];
        });
    }

    /**
     * Get the allowed settings for key.
     *
     * @param string $key
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAllowed($key)
    {
        if ($this->isRegistered($key)) {
            return collect($this->getRegistered()[$key]['allowed']);
        }
    }

    /**
     * Get all allowed values for settings.
     *
     * @return \Illuminate\Support\Collection
     */
    public function allAllowed()
    {
        return $this->getRegistered()->map(function ($value) {
            return $value['allowed'];
        });
    }

    /**
     * Get all saved settings. Default values are not included in this output.
     *
     * @return \Illuminate\Support\Collection
     */
    public function allSaved()
    {
        return collect($this->settings);
    }

    /**
     * Update or add multiple values to the settings table.
     *
     * @param array $attributes
     *
     * @return static
     */
    public function set(array $attributes)
    {
        collect($attributes)->each(function ($value, $key) {
            $this->setKeyValue($key, $value);
        });

        // If we were working with eagerly-loaded relation,
        // we need to reload its data to be sure that we
        // are working only with the actual settings.

        if ($this->resource->relationLoaded('propertyBag')) {
            $this->resource->load('propertyBag');
        }

        $this->flushCache();
        
        return $this->sync();
    }

    /**
     * Return true if key is set to value.
     *
     * @param string $key
     * @param string $value
     *
     * @return bool
     */
    public function keyIs($key, $value)
    {
        return $this->get($key) === $value;
    }

    /**
     * Reset key to default value. Return default value.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function reset($key)
    {
        $default = $this->getDefault($key);

        $this->set([$key => $default]);

        return $default;
    }

    /**
     * Set a value to a key in local and database settings.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    protected function setKeyValue($key, $value)
    {
        $this->validateKeyValue($key, $value);

        if ($this->isDefault($key, $value) && $this->isSaved($key)) {
            return $this->deleteRecord($key);
        } elseif ($this->isDefault($key, $value)) {
            return;
        } elseif ($this->isSaved($key)) {
            return $this->updateRecord($key, $value);
        }

        return $this->createRecord($key, $value);
    }

    /**
     * Throw exception if key/value invalid.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @throws InvalidSettingsValue
     */
    protected function validateKeyValue($key, $value)
    {
        if (!$this->isValid($key, $value)) {
            throw InvalidSettingsValue::settingNotAllowed($key);
        }
    }

    /**
     * Return true if key is already saved in database.
     *
     * @param string $key
     *
     * @return bool
     */
    public function isSaved($key)
    {
        return $this->allSaved()->has($key);
    }

    /**
     * Create a new PropertyBag record.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return \LaravelPropertyBag\Settings\PropertyBag
     */
    protected function createRecord($key, $value)
    {
        $result = $this->propertyBag()->save(
            new PropertyBag([
                'key'   => $key,
                'value' => $this->valueToJson($value),
            ])
        );
        
        $this->flushCache();
        
        return $result;
    }

    /**
     * Update a PropertyBag record.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return \LaravelPropertyBag\Settings\PropertyBag
     */
    protected function updateRecord($key, $value)
    {
        $record = $this->getByKey($key);

        $record->value = $this->valueToJson($value);

        $record->save();
        
        $this->flushCache();

        return $record;
    }

    /**
     * Json encode value.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function valueToJson($value)
    {
        return json_encode([$value]);
    }

    /**
     * Delete a PropertyBag record.
     *
     * @param string $key
     *
     * @return bool
     */
    protected function deleteRecord($key)
    {
        $this->getByKey($key)->delete();
        
        $this->flushCache();
    }

    /**
     * Get a property bag record by key.
     *
     * @param string $key
     *
     * @return \LaravelPropertyBag\Settings\PropertyBag
     */
    protected function getByKey($key)
    {
        return $this->propertyBag()
            ->where('resource_id', $this->resource->getKey())
            ->where('key', $key)
            ->first();
    }

    /**
     * Load settings from the resource relationship on to this.
     */
    protected function sync()
    {
        if (!$this->isCacheEnabled()) {
            $this->settings = $this->getAllSettingsFlat();
            return;
        }

        $cacheKey = $this->getAllSavedCacheKey();
        
        $this->settings = $this->cacheRemember($cacheKey, function () {
            return $this->getAllSettingsFlat();
        });
    }

    /**
     * Get all settings as a flat collection.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getAllSettingsFlat()
    {
        return $this->getAllSettings()->flatMap(function (Model $model) {
            return [$model->key => json_decode($model->value)[0]];
        });
    }

    /**
     * Retrieve all settings from database.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getAllSettings()
    {
        if ($this->resource->relationLoaded('propertyBag')) {
            return $this->resource->propertyBag;
        }

        return $this->propertyBag()
            ->where('resource_id', $this->resource->getKey())
            ->get();
    }

    /**
     * Get value from settings by key. Get registered default if not set.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get($key)
    {
        if (!$this->isCacheEnabled()) {
            return $this->getFromDatabase($key);
        }

        $cacheKey = $this->getCacheKey($key);
        
        return $this->cacheRemember($cacheKey, function () use ($key) {
            return $this->getFromDatabase($key);
        });
    }

    /**
     * Get value from database.
     *
     * @param string $key
     *
     * @return mixed
     */
    protected function getFromDatabase($key)
    {
        return $this->allSaved()->get($key, function () use ($key) {
            return $this->getDefault($key);
        });
    }

    /**
     * Get cache store instance.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    protected function getCacheStore()
    {
        $store = config('propertybag.cache.store');
        
        return $store ? Cache::store($store) : Cache::store();
    }

    /**
     * Check if cache is enabled.
     *
     * @return bool
     */
    protected function isCacheEnabled()
    {
        return config('propertybag.cache.enabled', true);
    }


    /**
     * Get cache key for a specific setting.
     *
     * @param string $key
     *
     * @return string
     */
    protected function getCacheKey($key)
    {
        $resourceType = get_class($this->resource);
        $resourceId = $this->resource->getKey();
        
        return "property_bag:{$resourceType}:{$resourceId}:{$key}";
    }

    /**
     * Get cache key for all settings.
     *
     * @return string
     */
    protected function getAllCacheKey()
    {
        $resourceType = get_class($this->resource);
        $resourceId = $this->resource->getKey();
        
        return "property_bag:{$resourceType}:{$resourceId}:all";
    }

    /**
     * Get cache key for all saved settings.
     *
     * @return string
     */
    protected function getAllSavedCacheKey()
    {
        $resourceType = get_class($this->resource);
        $resourceId = $this->resource->getKey();
        
        return "property_bag:{$resourceType}:{$resourceId}:saved";
    }


    /**
     * Remember value in cache.
     *
     * @param string   $key
     * @param \Closure $callback
     *
     * @return mixed
     */
    protected function cacheRemember($key, \Closure $callback)
    {
        $duration = config('propertybag.cache.duration', 86400);
        
        // Track this cache key for later invalidation
        $this->trackCacheKey($key);
        
        return $this->cache->remember($key, $duration, $callback);
    }

    /**
     * Track a cache key for this resource.
     *
     * @param string $key
     *
     * @return void
     */
    protected function trackCacheKey($key)
    {
        $resourceType = get_class($this->resource);
        
        // Track by resource type
        $typeKeysKey = "property_bag:keys:{$resourceType}";
        $typeKeys = $this->cache->get($typeKeysKey, []);
        if (!in_array($key, $typeKeys)) {
            $typeKeys[] = $key;
            $this->cache->put($typeKeysKey, $typeKeys, config('propertybag.cache.duration', 86400));
        }
        
        // Track resource types
        $resourceTypesKey = 'property_bag:resource_types';
        $resourceTypes = $this->cache->get($resourceTypesKey, []);
        if (!in_array($resourceType, $resourceTypes)) {
            $resourceTypes[] = $resourceType;
            $this->cache->put($resourceTypesKey, $resourceTypes, config('propertybag.cache.duration', 86400));
        }
        
        // Track by specific resource
        $resourceId = $this->resource->getKey();
        $resourceKeysKey = "property_bag:keys:{$resourceType}:{$resourceId}";
        $resourceKeys = $this->cache->get($resourceKeysKey, []);
        if (!in_array($key, $resourceKeys)) {
            $resourceKeys[] = $key;
            $this->cache->put($resourceKeysKey, $resourceKeys, config('propertybag.cache.duration', 86400));
        }
    }

    /**
     * Flush cache for this resource.
     *
     * @return void
     */
    protected function flushCache()
    {
        if (!$this->isCacheEnabled()) {
            return;
        }

        $this->forgetCacheKeys();
    }

    /**
     * Forget individual cache keys.
     *
     * @return void
     */
    protected function forgetCacheKeys()
    {
        $resourceType = get_class($this->resource);
        $resourceId = $this->resource->getKey();
        $resourceKeysKey = "property_bag:keys:{$resourceType}:{$resourceId}";
        
        // Get all tracked keys for this resource
        $trackedKeys = $this->cache->get($resourceKeysKey, []);
        
        // Forget all tracked keys
        foreach ($trackedKeys as $key) {
            $this->cache->forget($key);
        }
        
        // Also forget the saved settings cache
        $this->cache->forget($this->getAllSavedCacheKey());
        
        // Clean up the tracking
        $this->cache->forget($resourceKeysKey);
        
        // Also update the resource type keys
        $typeKeysKey = "property_bag:keys:{$resourceType}";
        $typeKeys = $this->cache->get($typeKeysKey, []);
        $remainingKeys = array_filter($typeKeys, function($key) use ($trackedKeys) {
            return !in_array($key, $trackedKeys);
        });
        
        if (!empty($remainingKeys)) {
            $this->cache->put($typeKeysKey, array_values($remainingKeys), config('propertybag.cache.duration', 86400));
        } else {
            $this->cache->forget($typeKeysKey);
        }
    }

    /**
     * Flush cache for all resources of a specific type.
     *
     * @param string $resourceType Fully qualified class name
     * @param array|null $resourceIds Optional array of specific resource IDs to flush
     *
     * @return void
     */
    public static function flushCacheForResourceType($resourceType, $resourceIds = null)
    {
        if (!config('propertybag.cache.enabled', true)) {
            return;
        }

        $store = config('propertybag.cache.store');
        $cache = $store ? Cache::store($store) : Cache::store();
        
        // Get all cache keys for this resource type
        $cacheKeysKey = "property_bag:keys:{$resourceType}";
        $allKeys = $cache->get($cacheKeysKey, []);
        
        if ($resourceIds) {
            // Filter keys for specific resource IDs
            $pattern = "/property_bag:" . preg_quote($resourceType, '/') . ":([0-9]+):/";
            $keysToForget = array_filter($allKeys, function($key) use ($pattern, $resourceIds) {
                if (preg_match($pattern, $key, $matches)) {
                    return in_array($matches[1], $resourceIds);
                }
                return false;
            });
        } else {
            $keysToForget = $allKeys;
        }
        
        // Forget all matching keys
        foreach ($keysToForget as $key) {
            $cache->forget($key);
        }
        
        // Clean up the keys list
        if (!$resourceIds) {
            $cache->forget($cacheKeysKey);
        } else {
            $remainingKeys = array_diff($allKeys, $keysToForget);
            $cache->put($cacheKeysKey, array_values($remainingKeys), config('propertybag.cache.duration', 86400));
        }
    }

    /**
     * Flush all property bag cache.
     *
     * @return void
     */
    public static function flushAllCache()
    {
        if (!config('propertybag.cache.enabled', true)) {
            return;
        }

        $store = config('propertybag.cache.store');
        $cache = $store ? Cache::store($store) : Cache::store();
        
        // Get all resource types that have cached keys
        $resourceTypes = $cache->get('property_bag:resource_types', []);
        
        foreach ($resourceTypes as $resourceType) {
            static::flushCacheForResourceType($resourceType);
        }
        
        // Clean up the resource types list
        $cache->forget('property_bag:resource_types');
    }
}
