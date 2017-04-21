<?php
/**
 * Created by PhpStorm.
 * User: murrant
 * Date: 4/21/17
 * Time: 9:37 AM
 */

namespace LibreNMS\Device;

class WirelessSensor extends Sensor
{
    private $access_point_ip;

    public function __construct(
        $class,
        $device_id,
        $oids,
        $type,
        $index,
        $description,
        $access_point_id = null,
        $multiplier = 1,
        $divisor = 1,
        $aggregator = 'sum',
        $current = null,
        $entPhysicalIndex = null,
        $entPhysicalReference = null,
        $high_limit = null,
        $low_limit = null,
        $high_warn = null,
        $low_warn = null
    ) {
        $this->table = 'wireless_sensors';
        $this->access_point_ip = $access_point_id;
        parent::__construct($class, $device_id, $oids, $type, $index, $description, $multiplier, $divisor, $aggregator,
            $current, $entPhysicalIndex, $entPhysicalReference, $high_limit, $low_limit, $high_warn, $low_warn);
    }

    protected function toArray()
    {
        $sensor = parent::toArray();
        $sensor['access_point_id'] = $this->access_point_ip;
        return $sensor;
    }
}
