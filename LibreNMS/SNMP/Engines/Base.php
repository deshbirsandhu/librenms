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
use LibreNMS\Config;
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

    //FIXME probably not the right place for this...
    protected function prepSetting($device, $setting)
    {
        if (isset($device[$setting]) && is_numeric($device[$setting]) && $device[$setting] > 0) {
            return $device[$setting];
        }

        return Config::get("snmp.$setting");
    }

    /**
     * Get a collection of cache keys for the specified oids
     *
     * @param array|string $oids
     * @param string $tag Tag to group the cache by, usually Class::function
     * @param array $device
     * @return Collection Collection of keys indexed by oid
     */
    protected function getCacheKeys($oids, $tag, $device)
    {
        return collect($oids)->combine(collect($oids)->map(function ($oid) use ($tag, $device) {
            return Cache::genKey($tag, $oid, $device['device_id'], $device['community']);
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
            $parts = $info = explode('_', $key);
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
