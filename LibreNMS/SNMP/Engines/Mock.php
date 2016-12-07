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

use LibreNMS\SNMP\Contracts\SnmpEngine;
use LibreNMS\SNMP\DataSet;
use LibreNMS\SNMP\OIDData;

class Mock extends Base
{

    /**
     * @param array $device
     * @param string|array $oids single or array of oids to walk
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return string exact results from snmpget
     */
    public function getRaw($device, $oids, $options = null, $mib = null, $mib_dir = null)
    {
        // TODO: Implement getRaw() method.
    }

    /**
     * @param array $device
     * @param string $oid single oid to walk
     * @param string $options Options to send to snmpwalk
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return string exact results from snmpwalk
     */
    public function walkRaw($device, $oid, $options = null, $mib = null, $mib_dir = null)
    {
        // TODO: Implement walkRaw() method.
    }

    private function loadSnmpRec($community)
    {
        global $config;
        $data = DataSet::make();

        $contents = file_get_contents($config['install_dir'] . "/tests/snmpsim/$community.snmprec");
        $line = strtok($contents, "\r\n");
        while ($line !== false) {
            list($oid, $type, $value) = explode('|', $line, 3);
            if ($type == 4) {
                $value = trim($value);
            } elseif ($type == 6) {
                $value = trim($value, '.');
            }

            $data->push(OIDData::make(array(
                compact('oid', 'type', 'value')
            )));

            $line = strtok("\r\n");
        }

        return $data;
    }
}
