<?php
/**
 * WirelessSensor.php
 *
 * Wireless Sensors
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

namespace LibreNMS\Device;

use LibreNMS\RRD\RrdDefinition;

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
        $high_limit = null,
        $low_limit = null,
        $high_warn = null,
        $low_warn = null,
        $entPhysicalIndex = null,
        $entPhysicalReference = null
    ) {
        $this->table = 'wireless_sensors';
        $this->access_point_ip = $access_point_id;
        parent::__construct($class, $device_id, $oids, $type, $index, $description, $multiplier, $divisor, $aggregator,
            $current, $high_limit, $low_limit, $high_warn, $low_warn, $entPhysicalIndex, $entPhysicalReference);
    }

    protected function toArray()
    {
        $sensor = parent::toArray();
        $sensor['access_point_id'] = $this->access_point_ip;
        return $sensor;
    }

    /**
     * Poll all wireless sensors.
     * TODO: Use traits, also, could be optimized
     *
     * @param $device
     * @param $class
     */
    public static function poll($device, $class)
    {
        $sensors = dbFetchRows(
            "SELECT * FROM `wireless_sensors` WHERE `sensor_class` = ? AND `device_id` = ?",
            array($class, $device['device_id'])
        );

        foreach ($sensors as $sensor) {
            $oids = json_decode($sensor['sensor_oids']);
            $data = snmp_get_multi_oid($device, $oids);

            $sensor_value = current($data);
            if (count($data) > 1) {
                // aggregate data
                if ($sensor['sensor_aggregator'] == 'sum') {
                    $sensor_value = array_sum($data);
                } elseif ($sensor['sensor_aggregator'] == 'avg') {
                    $sensor_value = array_sum($data) / count($data);
                }
            }

            if ($sensor['sensor_divisor'] && $sensor_value !== 0) {
                $sensor_value = ($sensor_value / $sensor['sensor_divisor']);
            }

            if ($sensor['sensor_multiplier']) {
                $sensor_value = ($sensor_value * $sensor['sensor_multiplier']);
            }

            echo $sensor['sensor_descr'] . ': ' . $sensor_value . PHP_EOL;

            // update rrd and database
            $rrd_name = array(
                'wireless-sensor',
                $sensor['sensor_class'],
                $sensor['sensor_type'],
                $sensor['sensor_index']
            );
            $rrd_def = RrdDefinition::make()->addDataset('wireless-sensor', 'GAUGE', -20000, 20000);

            $fields = array(
                'wireless-sensor' => isset($sensor_value) ? $sensor_value : 'U',
            );

            $tags = array(
                'sensor_class' => $sensor['sensor_class'],
                'sensor_type' => $sensor['sensor_type'],
                'sensor_descr' => $sensor['sensor_descr'],
                'sensor_index' => $sensor['sensor_index'],
                'rrd_name' => $rrd_name,
                'rrd_def' => $rrd_def
            );
            data_update($device, 'wireless-sensor', $tags, $fields);

            $update = array(
                'sensor_prev' => $sensor['sensor_current'],
                'sensor_current' => $sensor_value,
                'lastupdate' => array('NOW()'),
            );
            dbUpdate($update, 'wireless_sensors', "`sensor_id` = ?", array($sensor['sensor_id']));
        }
    }
}
