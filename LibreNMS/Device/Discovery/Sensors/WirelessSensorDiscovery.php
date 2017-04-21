<?php
/**
 * Created by PhpStorm.
 * User: murrant
 * Date: 4/21/17
 * Time: 6:26 AM
 */

namespace LibreNMS\Device\Discovery\Sensors;

use LibreNMS\Device\Sensor;

interface WirelessSensorDiscovery
{
    /**
     * @return Sensor
     */
    public function discoverClients();
}
