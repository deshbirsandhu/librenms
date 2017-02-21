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

use Illuminate\Support\Collection;
use LibreNMS\Cache;
use LibreNMS\SNMP\Contracts\SnmpEngine;
use LibreNMS\SNMP\OIDData;

abstract class Base implements SnmpEngine
{
    /**
     * Gets the name of this SNMP Engine
     * @return string
     */
    public function getName()
    {
        $reflectionClass = new \ReflectionClass($this);
        return $reflectionClass->getShortName();
    }

    /**
     * Get a collection of cache keys for the specified oids
     *
     * @param array|string $oids
     * @param string $tag Tag to group the cache by, usually Class::function
     * @param array $device
     * @param string $extra extra data, such as command options
     * @return Collection Collection of keys indexed by oid
     */
    protected function getCacheKeys($oids, $tag, $device, $extra = null)
    {
        if ($extra) {
            $extra = '_' . str_replace(' ', '_', $extra);
        }
        $extra .= '_' . $device['community'];  // mostly for snmpsim, so v3 is not relevant

        return collect($oids)->combine(collect($oids)->map(function ($oid) use ($tag, $device, $extra) {
            return Cache::genKey($tag, $oid, $device['device_id'], $extra);
        }));
    }

    /**
     * Print an approximation of NetSNMP output
     * This will NOT match NetSNMP output exactly
     *
     * @param Collection|array $data should be indexed by the key or oid
     */
    protected function printDebug($data)
    {
        global $debug;
        if (!$debug || !$data instanceof Collection) {
            return;
        }

        foreach ($data as $key => $oid_data) {
            // explode cache key if we have one.
            $parts = explode('_', $key);
            if (count($parts) == 3) {
                list($cacher, $device_id, $oids) = $parts;
                $snmpinfo = "Cached by $cacher device_id:$device_id oids:$oids";
            } else {
                $snmpinfo = $key;
            }
            c_echo("SNMP[%c$snmpinfo%n]\n[");

            if (is_string($oid_data)) {
                echo $oid_data;
            } else {
                $oid_data->each(function ($entry) {
                    if ($entry instanceof OIDData) {
                        echo "{$entry->oid} = {$entry->type}: {$entry->value}\n";
                    } else {
                        var_dump($entry);
                    }
                });
            }
            echo "]\n";
        }
    }
}
