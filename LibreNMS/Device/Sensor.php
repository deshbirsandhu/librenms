<?php
/**
 * Sensor.php
 *
 * Base Sensor class
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

class Sensor
{
    protected $table = 'sensors';
    private $sensor_id;

    private $class;
    private $device_id;
    private $oids;
    private $type;
    private $index;
    private $multiplier;
    private $divisor;
    private $aggregator;
    private $current;
    private $entPhysicalIndex;
    private $entPhysicalReference;
    private $high_limit;
    private $low_limit;
    private $high_warn;
    private $low_warn;
    private $description;


    public function __construct(
        $class,
        $device_id,
        $oids,
        $type,
        $index,
        $description,
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
        //
        $this->class = $class;
        $this->device_id = $device_id;
        $this->oids = (array)$oids;
        $this->type = $type;
        $this->index = $index;
        $this->description = $description;
        $this->multiplier = $multiplier;
        $this->divisor = $divisor;
        $this->aggregator = $aggregator;
        $this->current = $current;
        $this->entPhysicalIndex = $entPhysicalIndex;
        $this->entPhysicalReference = $entPhysicalReference;
        $this->high_limit = $high_limit;
        $this->low_limit = $low_limit;
        $this->high_warn = $high_warn;
        $this->low_warn = $low_warn;
    }

    /**
     * Save this sensor to the database.
     *
     * @return int the sensor_id of this sensor in the database
     */
    public function save()
    {
        $db_sensor = $this->fetch();

        $new_sensor = $this->toArray();
        if ($db_sensor) {
            $update = array_diff_assoc($new_sensor, $db_sensor);
            if (empty($update)) {
                echo '.';
            } else {
                dbUpdate($this->escapeNull($update), $this->table, '`sensor_id`=?', array($this->sensor_id));
                echo 'U';
            }
        } else {
            $this->sensor_id = dbInsert($this->escapeNull($new_sensor), $this->table);
            echo '+';
        }

        return $this->sensor_id;
    }

    /**
     * Fetch the sensor from the database.
     * If it doesn't exist, returns null.
     *
     * @return array|null
     */
    private function fetch()
    {
        if (isset($this->sensor_id)) {
            return dbFetchRow(
                "SELECT `{$this->table}` FROM ? WHERE `sensor_id`=?",
                array($this->sensor_id)
            );
        }

        $sensor = dbFetchRow(
            "SELECT * FROM `{$this->table}` " .
            "WHERE `device_id`=? AND `sensor_class`=? AND `sensor_type`=? AND `sensor_index`=?",
            array($this->device_id, $this->class, $this->type, $this->index)
        );
        $this->sensor_id = $sensor['sensor_id'];
        return $sensor;
    }

    /**
     * Get an array of this sensor with fields that line up with the database.
     *
     * @return array
     */
    protected function toArray()
    {
        return array(
            'sensor_class' => $this->class,
            'device_id' => $this->device_id,
            'sensor_oids' => json_encode($this->oids),
            'sensor_index' => $this->index,
            'sensor_type' => $this->type,
            'sensor_descr' => $this->description,
            'sensor_divisor' => $this->divisor,
            'sensor_multiplier' => $this->multiplier,
            'sensor_aggregator' => $this->aggregator,
            'sensor_limit' => $this->high_limit,
            'sensor_limit_warn' => $this->high_warn,
            'sensor_limit_low' => $this->low_limit,
            'sensor_limit_low_warn' => $this->low_warn,
            'sensor_current' => $this->current,
            'entPhysicalIndex' => $this->entPhysicalIndex,
            'entPhysicalIndex_measured' => $this->entPhysicalReference,
        );
    }

    /**
     * Escape null values so dbFacile doesn't mess them up
     * honestly, this should be the default, but could break shit
     *
     * @param $array
     * @return array
     */
    private function escapeNull($array)
    {
        return array_map(function ($value) {
            return is_null($value) ? array('NULL') : $value;
        }, $array);
    }

    /**
     * Save sensors and remove invalid sensors
     * This the sensors array should contain all the sensors of a specific class
     * It may contain sensors from multiple tables and devices, but that isn't the primary use
     *
     * @param array $sensors
     */
    public static function sync(array $sensors)
    {
        // save and group up the sensors, generally, there will be only one group
        $valid_sensors = array();
        foreach ($sensors as $sensor) {
            /** @var $this $sensor */
            $valid_sensors[$sensor->table][$sensor->device_id][$sensor->class][] = $sensor->save();
        }

        // delete invalid sensors
        foreach ($valid_sensors as $table => $device_ids) {
            foreach ($device_ids as $device_id => $classes) {
                foreach ($classes as $class => $sensor_ids) {
                    self::clean($table, $device_id, $class, $sensor_ids);
                }
            }
        }
    }

    /**
     * Remove a group of sensors
     *
     * @param $table
     * @param $device_id
     * @param $class
     * @param $sensor_ids
     */
    private static function clean($table, $device_id, $class, $sensor_ids)
    {
        $placeholders = dbGenPlaceholders(count($sensor_ids));
        $params = array_merge(array($device_id, $class), $sensor_ids);
        $where = "`device_id`=? AND `sensor_class`=? AND `sensor_id` NOT IN $placeholders";
        $sql = "SELECT * FROM `$table` WHERE $where";

        foreach (dbFetchRows($sql, $params) as $sensor) {
            echo '-';
            $message = "Wireless Sensor Deleted: $class {$sensor->type} {$sensor->index} {$sensor->description}";
            log_event($message, $device_id, 'sensor', 3, $sensor->sensor_id);
        }
        dbDelete($table, $where, $params);
    }
}
