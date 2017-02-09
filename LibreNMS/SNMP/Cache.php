<?php
/**
 * Cache.php
 *
 * -Description-
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

namespace LibreNMS\SNMP;

use phpFastCache\CacheManager;

class Cache
{
    /**
     * Retrieve data from the cache or fetch the data with the $callback
     * and cache the results.
     *
     * @param string $key key used to identify the data
     * @param callable $callback function that will fetch the data if it is not cached
     * @param int $time override default cache time
     * @return mixed
     */
    public static function getOrFetch($key, $callback, $time = null)
    {
        global $config, $debug;

        if (!$config['snmp']['cache']) {
            return call_user_func($callback);
        }

        $cache = CacheManager::getInstance();

        /** @var DataSet $cached_result */
        $cached_result = $cache->get($key);


        if (is_null($cached_result)) {
            $result = call_user_func($callback);
            if (is_null($time)) {
                $time = $config['snmp']['cache_time'];
            }

            $cache->set($key, $result, $time);
            return $result;
        }

        if ($debug) {
            // FIXME: Don't print community
            c_echo("SNMP[%cCached $key%n]\n[");
            if (is_string($cached_result)) {
                echo $cached_result;
            } else {
                $cached_result->each(function ($entry) {
                    print_r($entry);
                    echo $entry->rawOid . $entry->rawValue;
                });
            }
            echo "]\n";
        }

        return $cached_result;
    }

    public static function get($key)
    {
        return CacheManager::getInstance()->get($key);
    }

    public static function put($key, $value, $time = null)
    {
        global $debug;

        if (is_null($time)) {
            global $config;
            $time = $config['snmp']['cache_time'];
        }

        CacheManager::getInstance()->set($key, $value, $time);

        if ($debug) {
            echo "Cached $key:\n";
            var_dump(CacheManager::getInstance()->get($key));
        }
    }

    /**
     * Check if the key is cached.
     *
     * @param $key
     * @return bool
     */
    public static function has($key)
    {
        return CacheManager::getInstance()->isExisting($key);
    }

    /**
     * Generate a string to use as the key
     *
     * @param string $group generally, this is the function name returned by __FUNCTION__
     * @param array|string $oids oid or array of oids
     * @param int|string $device_id The id of the device
     * @param string $extra extra string, such as command options or anything that might vary your data
     * @return string the resulting key string
     */
    public static function genKey($group, $oids, $device_id = '', $extra = '')
    {
        return $group . $device_id . implode((array)$oids) . $extra;
    }
}
