<?php
/**
 * Airos.php
 *
 * Ubiquiti AirOS functions
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
use LibreNMS\Interfaces\Discovery\Sensors\WirelessNoiseFloorDiscovery;
use LibreNMS\OS;

class Airos extends OS implements WirelessClientsDiscovery, WirelessNoiseFloorDiscovery, WirelessCcqDiscovery
{
    /**
     * Returns an array of LibreNMS\Device\Sensor objects that have been discovered
     *
     * @return array Sensors
     */
    public function discoverWirelessClients()
    {
        $oid = '.1.3.6.1.4.1.41112.1.4.5.1.15.1'; //UBNT-AirMAX-MIB::ubntWlStatStaCount.1
        $count = snmp_get($this->getDevice(), $oid, '-Oqv');

        if (is_numeric($count)) {
            return array(
                new WirelessSensor('clients', $this->getDeviceId(), $oid, 'airos', 1, 'Clients', $count)
            );
        }

        return array();
    }

    /**
     * Returns an array of LibreNMS\Device\Sensor objects that have been discovered
     *
     * @return array
     */
    public function discoverWirelessNoiseFloor()
    {
        $oid = '.1.3.6.1.4.1.41112.1.4.5.1.8.1'; //UBNT-AirMAX-MIB::ubntWlStatNoiseFloor.1
        $noise_floor = snmp_get($this->getDevice(), $oid, '-Oqv');

        if (is_numeric($noise_floor)) {
            return array(
                new WirelessSensor('noise-floor', $this->getDeviceId(), $oid, 'airos', 1, 'Noise Floor', $noise_floor)
            );
        }

        return array();
    }

    /**
     * Returns an array of LibreNMS\Device\Sensor objects that have been discovered
     *
     * @return array Sensors
     */
    public function discoverWirelessCcq()
    {
        $oid = '.1.3.6.1.4.1.41112.1.4.5.1.7.1'; //UBNT-AirMAX-MIB::ubntWlStatCcq.1
        $ccq = snmp_get($this->getDevice(), $oid, '-Oqv');

        if (is_numeric($ccq)) {
            return array(
                new WirelessSensor('ccq', $this->getDeviceId(), $oid, 'airos', 1, 'CCQ', $ccq)
            );
        }

        return array();
    }
}
