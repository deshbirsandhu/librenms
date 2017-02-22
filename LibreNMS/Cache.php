<?php
/**
 * Cache.php
 *
 * Cache convenience functions
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2017 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS;

use Illuminate\Support\Collection;
use phpFastCache\CacheManager;

class Cache
{
    /**
     * Configure and initialize the cache
     * Uses memcache , apc if available, falls back to file cache
     */
    public static function setup()
    {
        // determine caching method
        if (Config::get('memcached.enable')) {
            $storage = 'memcached';
        } elseif (extension_loaded('apc') && ini_get('apc.enabled')) {
            $storage = 'apc';
        } else {
            $storage = 'files';
        }

        // Setup File Path on your config files
        $setup = array(
            'storage' => $storage,
            'path' => Config::get('cache.dir'),
//            'allow_search' => true,
            'server' => array(
                array(Config::get('memcached.host'), Config::get('memcached.port'), 1)
            ),
            'fallback' => array(
                'memcache' => 'files',
                'predis' => 'files',
                'redis' => 'files',
                'apc' => 'files',
            )
        );
        CacheManager::setup($setup);
    }

    /**
     * Remove all data from the cache
     */
    public static function clear()
    {
        CacheManager::getInstance()->clean();
    }

    /**
     * Retrieve data from the cache or fetch the data with the $callback
     * and cache the results.
     *
     * @param string $key key used to identify the data
     * @param callable $callback function that will fetch the data if it is not cached
     * @param int $time in seconds (0 = forever), uses cache.time if unset
     * @return mixed
     */
    public static function remember($key, $callback, $time = null)
    {
        if (self::cacheDisabled()) {
            return call_user_func($callback);
        }

        $cache = CacheManager::getInstance();
        $cached_result = $cache->get($key);


        if (is_null($cached_result)) {
            $result = call_user_func($callback);
            $cache->set($key, $result, self::getCacheTime($time));
            d_echo("Cached $key\n");
            return $result;
        }

        return $cached_result;
    }

    /**
     * Check if the cache should be disabled
     *
     * @return bool
     */
    private static function cacheDisabled()
    {
        global $debug;
        return $debug || !Config::get('cache.enable');
    }

    /**
     * Check if the specified time is valid
     * return it or the system cache.time
     *
     * @param int $time
     * @return int
     */
    private static function getCacheTime($time)
    {
        if (is_null($time)) {
            return Config::get('cache.time');
        }
        return $time;
    }

    /**
     * Return a collection of cached data from the specified keys
     * The returned collection will retain the same indexes.
     * If no data is cached, that entry will be removed from the result
     *
     * @param Collection $keys
     * @return Collection key->cached data where key is the same as the input
     */
    public static function getMany(Collection $keys)
    {
        if (self::cacheDisabled()) {
            return null;
        }

        return $keys->map(function ($key) {
            return Cache::get($key);
        })->reject(function ($data) {
            return is_null($data);
        });
    }

    /**
     * Retrieve a value from the cache
     * Non-existent items will return null
     *
     * @param string $key key used to identify the data
     * @return mixed|null
     */
    public static function get($key)
    {
        if (self::cacheDisabled()) {
            return null;
        }

        return CacheManager::getInstance()->get($key);
    }

    /**
     * Save a value to the cache
     *
     * @param string $key key used to identify the data
     * @param mixed $value the data to store
     * @param int $time in seconds (0 = forever), uses cache.time if unset
     */
    public static function put($key, $value, $time = null)
    {
        if (self::cacheDisabled()) {
            return;
        }

        CacheManager::getInstance()->set($key, $value, self::getCacheTime($time));
        d_echo("Cached $key\n");
    }

    /**
     * Check if the key is cached.
     *
     * @param $key
     * @return bool
     */
    public static function has($key)
    {
        if (self::cacheDisabled()) {
            return false;
        }

        return CacheManager::getInstance()->isExisting($key);
    }

    /**
     * Generate a string to use as the key. For SNMP Engines.
     *
     * @param string $group generally, this is the function name returned by __FUNCTION__
     * @param array|string $oids oid or array of oids
     * @param int|string $device_id The id of the device
     * @param string $extra extra string, such as command options or anything that might vary your data
     * @return string the resulting key string
     */
    public static function genKey($group, $oids, $device_id = '', $extra = '')
    {
        return "{$group}_{$device_id}_" . implode('-', (array)$oids) . "_{$extra}";
    }
}
