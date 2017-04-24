<?php
/**
 * Unifi.php
 *
 * Ubiquiti Unifi functions
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

namespace LibreNMS\OS;

use LibreNMS\Device\WirelessSensor;
use LibreNMS\Interfaces\Discovery\Sensors\WirelessCcqDiscovery;
use LibreNMS\Interfaces\Discovery\Sensors\WirelessClientsDiscovery;
use LibreNMS\Interfaces\Polling\Sensors\WirelessCcqPolling;
use LibreNMS\OS;

class Unifi extends OS implements WirelessClientsDiscovery, WirelessCcqDiscovery, WirelessCcqPolling
{
    private $ccqDivisor = 10;

    /**
     * Returns an array of LibreNMS\Device\Sensor objects
     *
     * @return array Sensors
     */
    public function discoverWirelessClients()
    {
        // TODO clients could be discovered by ssid instead of or in addition to radio type

        $client_oids = snmpwalk_cache_oid($this->getDevice(), 'unifiVapNumStations', array(), 'UBNT-UniFi-MIB');
        if (empty($client_oids)) {
            return array();
        }
        $vap_radios = $this->getCacheData('unifiVapRadio');

        $radios = array();
        foreach ($client_oids as $index => $entry) {
            $radio_name = $vap_radios[$index];
            $radios[$radio_name]['oids'][] = '.1.3.6.1.4.1.41112.1.6.1.2.1.8.' . $index;
            $radios[$radio_name]['count'] += $entry['unifiVapNumStations'];
        }

        $sensors = array();
        foreach ($radios as $index => $data) {
            $sensors[] = new WirelessSensor(
                'clients',
                $this->getDeviceId(),
                $data['oids'],
                'unifi',
                $index,
                strtoupper($index) . ' Radio',
                $data['count'],
                1,
                1,
                'sum',
                null,
                40,
                null,
                30
            );
        }
        return $sensors;
    }

    /**
     * Returns an array of LibreNMS\Device\Sensor objects that have been discovered
     *
     * @return array
     */
    public function discoverWirelessCcq()
    {
        $ccq_oids = snmpwalk_cache_oid($this->getDevice(), 'unifiVapCcq', array(), 'UBNT-UniFi-MIB');
        if (empty($ccq_oids)) {
            return array();
        }
        $ssids = $this->getCacheData('unifiVapEssId');

        $sensors = array();
        foreach ($ccq_oids as $index => $entry) {
            $ccq = $entry['unifiVapCcq'];
            if ($ssids[$index]) { // don't discover ssids with empty names
                $sensors[] = new WirelessSensor(
                    'ccq',
                    $this->getDeviceId(),
                    '.1.3.6.1.4.1.41112.1.6.1.2.1.3.'.$index,
                    'unifi',
                    $index,
                    $ssids[$index],
                    min($entry['unifiVapCcq'] / $this->ccqDivisor, 100),
                    1,
                    $this->ccqDivisor
                );
            }
        }
        return $sensors;
    }

    /**
     * Poll wireless client connection quality
     * The returned array should be sensor_id => value pairs
     *
     * @param array $sensors Array of sensors needed to be polled
     * @return array of polled data
     */
    public function pollWirelessCcq(array $sensors)
    {
        $ccq_oids = snmpwalk_cache_oid($this->getDevice(), 'unifiVapCcq', array(), 'UBNT-UniFi-MIB');

        $data = array();
        foreach ($sensors as $sensor) {
            $index = $sensor['sensor_index'];
            $data[$sensor['sensor_id']] = min($ccq_oids[$index]['unifiVapCcq'] / $this->ccqDivisor, 100);
        }

        return $data;
    }


    /**
     * Fetch data for a Unifi mib entry
     * If cached, return that, otherwise fetch via snmp
     *
     * Data will be indexed by the snmp index the value will be the raw data (not an array).
     *
     * @param $mibName
     * @return array
     */
    private function getCacheData($mibName)
    {
        if (!isset($this->$mibName)) {
            $oids = snmpwalk_cache_oid($this->getDevice(), $mibName, array(), 'UBNT-UniFi-MIB');

            $data = array();
            foreach ($oids as $index => $oid) {
                $data[$index] = $oid[$mibName];
            }
            $this->$mibName = $data;
        }

        return $this->$mibName;
    }
}
