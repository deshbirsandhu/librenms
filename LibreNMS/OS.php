<?php
/**
 * OS.php
 *
 * Base OS class
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

namespace LibreNMS;

use LibreNMS\Device\Discovery\Sensors\WirelessSensorDiscovery;
use LibreNMS\Device\Discovery\Sensors\WirelessSensorPolling;
use LibreNMS\Device\WirelessSensor;
use LibreNMS\OS\Generic;

class OS
{
    private $device; // annoying use of references to make sure this is in sync with global $device variable

    /**
     * OS constructor. Not allowed to be created directly.  Use OS::make()
     * @param $device
     */
    private function __construct(&$device)
    {
        $this->device = &$device;
    }

    /**
     * Get the device array that owns this OS instance
     *
     * @return array
     */
    public function getDevice()
    {
        return $this->device;
    }

    /**
     * Get the device_id of the device that owns this OS instance
     *
     * @return int
     */
    public function getDeviceId()
    {
        return (int)$this->device['device_id'];
    }

    /**
     * Run all discovery for this device.
     * Currently only supports WirelessSensors
     */
    public function runDiscovery()
    {

        // if module enabled
        WirelessSensor::discover($this);
    }

    /**
     * Run all polling for this device
     * Currently only supports WirelessSensors
     */
    public function runPolling(&$graphs)
    {
        // if module enabled
        WirelessSensor::poll($this, $graphs);
    }

    /**
     * OS Factory, returns the instance of this OS
     *
     * @param array $device device array, must have os set
     * @return OS|null
     */
    public static function make(&$device)
    {
        static $inst = null;
        if ($inst === null) {
            $class = string_to_class($device['os'], 'LibreNMS\\OS\\');
            if (class_exists($class)) {
                $inst = new $class($device);
            } else {
                $inst = new Generic($device);
            }
        }
        return $inst;
    }
}
