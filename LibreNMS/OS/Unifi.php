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

use LibreNMS\Device\Discovery\Sensors\WirelessSensorDiscovery;
use LibreNMS\Device\WirelessSensor;
use LibreNMS\OS;

class Unifi extends OS implements WirelessSensorDiscovery
{

    /**
     * Returns an array of LibreNMS\Device\Sensor objects
     *
     * @return array Sensors
     */
    public function discoverClients()
    {
        $device = $this->getDevice();
        $client_oids = snmpwalk_cache_oid($device, 'UBNT-UniFi-MIB::unifiVapRadio', array());
        $client_oids = snmpwalk_cache_oid($device, 'UBNT-UniFi-MIB::unifiVapNumStations', $client_oids);

        $radios = array();
        foreach ($client_oids as $index => $entry) {
            $radio_name = $entry['unifiVapRadio'];
            $radios[$radio_name]['oids'][] = '.1.3.6.1.4.1.41112.1.6.1.2.1.8.' . $index;
            $radios[$radio_name]['count'] += $entry['unifiVapNumStations'];
        }

        $sensors = array();
        foreach ($radios as $index => $data) {
            $sensors = new WirelessSensor(
                'clients',
                $device['device_id'],
                $data['oids'],
                'unifi',
                $index,
                strtoupper($index) . ' Radio',
                null,
                1,
                1,
                'sum',
                $data['count'],
                40,
                null,
                30
            );
        }
        return $sensors;
    }
}
