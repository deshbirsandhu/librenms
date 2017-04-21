<?php
/**
 * Created by PhpStorm.
 * User: murrant
 * Date: 4/21/17
 * Time: 6:25 AM
 */

namespace LibreNMS\OS;

use LibreNMS\Device\Discovery\Sensors\WirelessSensorDiscovery;
use LibreNMS\Device\Sensor;

class Ios implements WirelessSensorDiscovery
{

    /**
     * @return Sensor
     */
    public function discoverClients()
    {
        // TODO: Implement discoverClients() method.
    }
}
