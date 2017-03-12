<?php
/**
 * Base.php
 *
 * A base implementation of get() and walk(),  children need to supply getRaw() and walkRaw()
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
 * @copyright  2016 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\SNMP\Engines;

use LibreNMS\Cache;
use LibreNMS\Exceptions\SnmpUnreachableException;
use LibreNMS\SNMP;
use LibreNMS\SNMP\DataSet;
use LibreNMS\SNMP\Parse;

abstract class RawBase extends Base
{
    /**
     * Get SNMP DataSet, will return cached result if it is available
     *
     * @param array $device
     * @param string|array $oids single or array of oids to get
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return DataSet collection of results
     */
    public function get($device, $oids, $mib = null, $mib_dir = null)
    {
        try {
            $oid_cache_keys = $this->getCacheKeys($oids, 'RawBase::get', $device);

            $cached = Cache::getMany($oid_cache_keys);
            $this->printDebug($cached);

            $oids_to_fetch = $oid_cache_keys->diffKeys($cached)->keys();
            $fetched = $oids_to_fetch
                ->combine(Parse::rawOutput($this->getRaw($device, $oids_to_fetch->all(), '-OSX', $mib, $mib_dir)));

            // cache the results individually
            $fetched->each(function ($entry, $oid) use ($oid_cache_keys) {
                d_echo("Caching $oid\n");
                Cache::put($oid_cache_keys->get($oid), $entry);
            });

            $result = $fetched->merge($cached);

            // put things in the correct order
            $result = $oid_cache_keys->keys()->map(function ($oid) use ($result) {
                return $result->get($oid);
            })->values();

            return (count((array)$oids) == 1 && $result->count() == 1) ? $result->first() : DataSet::make($result);
        } catch (SnmpUnreachableException $e) {
            return DataSet::makeError(SNMP::ERROR_UNREACHABLE, $e->getMessage());
        }
    }


    /**
     * @param array $device
     * @param string|array $oids single or array of oids to walk
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return DataSet collection of results
     */
    public function walk($device, $oids, $mib = null, $mib_dir = null)
    {
        try {
            $results = DataSet::make();
            foreach ((array)$oids as $oid) {
                $key = Cache::genKey('RawBase::walk', $oid, $device['device_id'], $device['community']);
                if (Cache::has($key)) {
                    $results = $results->merge(Cache::get($key));
                } else {
                    $data = Parse::rawOutput($this->walkRaw($device, $oid, '-OSX', $mib, $mib_dir));
                    $results = $results->merge($data);
                    Cache::put($key, $data);
                }
            }
            return $results;
        } catch (SnmpUnreachableException $e) {
            return DataSet::makeError(SNMP::ERROR_UNREACHABLE, $e->getMessage());
        }
    }
}
