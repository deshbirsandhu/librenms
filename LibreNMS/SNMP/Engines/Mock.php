<?php
/**
 * Mock.php
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
use LibreNMS\Config;
use LibreNMS\SNMP;
use LibreNMS\SNMP\DataSet;
use LibreNMS\SNMP\Format;
use LibreNMS\SNMP\OIDData;
use LibreNMS\SNMP\Parse;

class Mock extends FormattedBase
{
    /** @var Collection */
    private $snmpRecData;

    public function __construct()
    {
        $this->snmpRecData = new Collection;
    }

    /**
     * @param array $device
     * @param string|array $oids single or array of oids to get
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return DataSet collection of results
     */
    public function get($device, $oids, $mib = null, $mib_dir = null)
    {
        $oids = is_array($oids) ? $oids : explode(' ', $oids);

        // fake unreachable
        if ($device['community'] == 'unreachable') {
            return DataSet::makeError(SNMP::ERROR_UNREACHABLE);
        }

        $numeric_oids = DataSet::make(SNMP::translateNumeric($device, $oids))->map(function ($value) {
            return ltrim($value, '.');
        });

        $data = $this->getSnmpRec($device['community'])
        ->filter(function ($entry) use ($numeric_oids) {
            // only keep data that is requested
            return in_array($entry->oid, $numeric_oids->all());
        })->map(function (SNMP\OIDData $item) use ($device) {
            // add oid variables to the data
            $oid = SNMP::translate($device, $item['oid']);
            return $item->merge(Parse::rawOID($oid));
        });

        // insert errors for missing data
        return $numeric_oids->map(function ($oid) use ($data, $device, $mib) {
            if ($data->has($oid)) {
                return Mock::formatOutput($data[$oid], $device, $mib);
            } else {
                return SNMP\OIDData::makeError(
                    SNMP::ERROR_NO_SUCH_OID,
                    'No Such Instance currently exists at this OID'
                );
            }
        })->values();
    }

    /**
     * Tweaks OIDData to match the same output as real snmp modules
     *
     * @param OIDData $oid_data
     * @param array $device
     * @param null $mib
     * @return OIDData
     */
    public static function formatOutput(OIDData $oid_data, $device = array(), $mib = null)
    {
        $type = $oid_data['type'];

        if ($type == 'hex-string') {
            $oid_data = $oid_data->merge(Parse::stringType(
                Format::hexStringAsString($oid_data['oid'], $oid_data['value'])
            ));
        } elseif ($type == 'oid') {
            $oid_data['value'] = '.' . ltrim($oid_data['value'], '.');
        }
        return $oid_data;
    }

    private function getSnmpRec($community)
    {
        if ($this->snmpRecData->has($community)) {
            return $this->snmpRecData[$community];
        }

        $data = DataSet::make();

        $contents = file_get_contents(Config::get('install_dir') . "/tests/snmpsim/$community.snmprec");
        $line = strtok($contents, "\r\n");
        while ($line !== false) {
            $entry = Parse::snmprec($line);
            $data[$entry['oid']] = $entry;

            $line = strtok("\r\n");
        }

        $this->snmpRecData[$community] = $data;
        return $data;
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
        $results = DataSet::make();
        foreach ((array)$oids as $oid) {
            $oid = ltrim(SNMP::translateNumeric($device, $oid), '.');
            $data = $this->getSnmpRec($device['community'])
                ->filter(function ($entry) use ($oid) {
                    // only keep data that is requested
                    return starts_with($entry->oid, $oid);
                })
                ->map(function (SNMP\OIDData $item) use ($device) {
                    // add oid variables to the data
                    $oid = SNMP::translate($device, $item['oid']);
                    return Mock::formatOutput($item->merge(Parse::rawOID($oid)));
                });
            if ($data->isEmpty()) {
                $results[] = SNMP\OIDData::makeError(
                    SNMP::ERROR_NO_SUCH_OID,
                    'No Such Object available on this agent at this OID'
                );
            } else {
                $results = $results->merge($data);
            }
        }

        return $results->values();
    }

    /**
     * Generate fake device for testing.
     *
     * @param string $community name of the snmprec file to load
     * @param int $port port for snmpsim, should be defined by SNMPSIM
     * @return array
     */
    public static function genDevice($community = null, $port = null)
    {
        return array(
            'device_id' => 1,
            'hostname' => '127.0.0.1',
            'snmpver' => 'v2c',
            'port' => $port ?: (getenv('SNMPSIM') ?: 11161),
            'timeout' => 3,
            'retries' => 0,
            'snmp_max_repeaters' => 10,
            'community' => $community,
            'os' => 'generic',
            'os_group' => '',
        );
    }

    public static function genOIDData($oid, $type, $data)
    {
        $result = Parse::rawOID($oid);

        $result = $result->merge(Parse::value($type, $data));

        return $result;
    }
}
