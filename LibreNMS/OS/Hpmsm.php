<?php
/**
 * Hpmsm.php
 *
 * HP Msm Wireless
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

use LibreNMS\Device\AccessPoint;
use LibreNMS\Device\WirelessSensor;
use LibreNMS\Interfaces\Discovery\AccessPointsDiscovery;
use LibreNMS\Interfaces\Discovery\Sensors\WirelessClientsDiscovery;
use LibreNMS\OS;

class Hpmsm extends OS implements WirelessClientsDiscovery, AccessPointsDiscovery
{
    /**
     * Returns an array of LibreNMS\Device\Sensor objects that have been discovered
     *
     * @return array Sensors
     */
    public function discoverWirelessClients()
    {
        return array(
            new WirelessSensor(
                'clients',
                $this->getDeviceId(),
                '.1.3.6.1.4.1.8744.5.25.1.7.2.0',
                'hpmsm',
                0,
                'Clients'
            )
        );
    }

    /**
     * Return an array of valid AccessPoint objects
     *
     * @return array AccessPoint
     */
    public function discoverAccessPoints()
    {
        // TODO some sort of check before a barrage of walks
        $device_oids = snmp_cache_oid('coDevDisSystemName', $this->getDevice(), array(), 'COLUBRIS-DEVICE-MIB');
        $device_oids = snmp_cache_oid('coDevDisMacAddress', $this->getDevice(), $device_oids, 'COLUBRIS-DEVICE-MIB');
        $radio_oids = snmpwalk_cache_twopart_oid($this->getDevice(), 'coDevWirIfStaTransmitPower', array(), 'COLUBRIS-DEVICE-WIRELESS-MIB');
//        $radio_oids = snmpwalk_cache_twopart_oid($this->getDevice(), 'coDevWirIfStaRadioType', $radio_oids, 'COLUBRIS-DEVICE-WIRELESS-MIB'); // type
        $radio_oids = snmpwalk_cache_twopart_oid($this->getDevice(), 'coDevWirIfStaNumberOfClient', $radio_oids, 'COLUBRIS-DEVICE-WIRELESS-MIB');
        $radio_oids = snmpwalk_cache_twopart_oid($this->getDevice(), 'coDevWirIfStaOperatingChannel', $radio_oids, 'COLUBRIS-DEVICE-WIRELESS-MIB');


        $aps = array();
        foreach ($device_oids as $device_index => $device_data) {
            foreach ($radio_oids[$device_index] as $radio_index => $radio_data) {
                $aps[] = new AccessPoint(
                    $device_data['coDevDisSystemName'],
                    $this->getDeviceId(),
                    $radio_index,
                    $this->channelToType($radio_data['coDevWirIfStaOperatingChannel']),
                    $device_data['coDevDisMacAddress'],
                    $radio_data['coDevWirIfStaOperatingChannel'],
                    $radio_data['coDevWirIfStaTransmitPower'],
                    0,
                    $radio_data['coDevWirIfStaNumberOfClient'],
                    0,
                    1,
                    1,
                    0
                );
            }
        }

        return $aps;
    }

    private function channelToType($channel)
    {
        if ($channel >= 1 && $channel <= 14) {
            return '2.4GHz';
        } elseif ($channel >= 35 && $channel <= 165) {
            return '5GHz';
        }
        return 'Unknown';
    }
}
