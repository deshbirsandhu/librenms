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

use LibreNMS\Interfaces\DiscoveryModule;
use LibreNMS\Interfaces\PollerModule;
use LibreNMS\OS;
use LibreNMS\RRD\RrdDefinition;

class Sensor implements DiscoveryModule, PollerModule
{
    protected static $name = 'Sensor';
    protected static $table = 'sensors';
    protected static $data_name = 'sensor';

    private $valid = true;

    private $sensor_id;

    private $type;
    private $device_id;
    private $oids;
    private $subtype;
    private $index;
    private $description;
    private $current;
    private $multiplier;
    private $divisor;
    private $aggregator;
    private $high_limit;
    private $low_limit;
    private $high_warn;
    private $low_warn;
    private $entPhysicalIndex;
    private $entPhysicalMeasured;

    /**
     * Sensor constructor. Create a new sensor to be discovered.
     *
     * @param string $type Class of this sensor, must be a supported class
     * @param int $device_id the device_id of the device that owns this sensor
     * @param array|string $oids an array or single oid that contains the data for this sensor
     * @param string $subtype the type of sensor an additional identifier to separate out sensors of the same class, generally this is the os name
     * @param int|string $index the index of this sensor, must be stable, generally the index of the oid
     * @param string $description A user visible description of this sensor, may be truncated in some places (like graphs)
     * @param int|float $current The current value of this sensor, will seed the db and may be used to guess limits
     * @param int $multiplier a number to multiply the value(s) by
     * @param int $divisor a number to divide the value(s) by
     * @param string $aggregator an operation to combine multiple numbers. Supported: sum, avg
     * @param int|float $high_limit Alerting: Maximum value
     * @param int|float $low_limit Alerting: Minimum value
     * @param int|float $high_warn Alerting: High warning value
     * @param int|float $low_warn Alerting: Low warning value
     * @param int|float $entPhysicalIndex The entPhysicalIndex this sensor is associated, often a port
     * @param int|float $entPhysicalMeasured the table to look for the entPhysicalIndex, for example 'ports' (maybe unused)
     */
    public function __construct(
        $type,
        $device_id,
        $oids,
        $subtype,
        $index,
        $description,
        $current = null,
        $multiplier = 1,
        $divisor = 1,
        $aggregator = 'sum',
        $high_limit = null,
        $low_limit = null,
        $high_warn = null,
        $low_warn = null,
        $entPhysicalIndex = null,
        $entPhysicalMeasured = null
    ) {
        //
        $this->type = $type;
        $this->device_id = $device_id;
        $this->oids = (array)$oids;
        $this->subtype = $subtype;
        $this->index = $index;
        $this->description = $description;
        $this->current = $current;
        $this->multiplier = $multiplier;
        $this->divisor = $divisor;
        $this->aggregator = $aggregator;
        $this->entPhysicalIndex = $entPhysicalIndex;
        $this->entPhysicalMeasured = $entPhysicalMeasured;
        $this->high_limit = $high_limit;
        $this->low_limit = $low_limit;
        $this->high_warn = $high_warn;
        $this->low_warn = $low_warn;

        // validity not checked yet
        if (is_null($this->current)) {
            $data = static::fetchSensorData(
                device_by_id_cache($device_id),
                array($this->toArray())
            );

            $this->current = current($data);
            $this->valid = is_numeric($this->current);
        }

        d_echo('Discovered ' . print_r($this, true));
    }

    /**
     * Save this sensor to the database.
     *
     * @return int the sensor_id of this sensor in the database
     */
    final public function save()
    {
        $db_sensor = $this->fetch();

        $new_sensor = $this->toArray();
        if ($db_sensor) {
            unset($new_sensor['sensor_current']); // if updating, don't check sensor_current
            $update = array_diff_assoc($new_sensor, $db_sensor);

            if ($db_sensor['sensor_custom'] == 'Yes') {
                unset($update['sensor_limit']);
                unset($update['sensor_limit_warn']);
                unset($update['sensor_limit_low']);
                unset($update['sensor_limit_low_warn']);
            }

            if (empty($update)) {
                echo '.';
            } else {
                dbUpdate($this->escapeNull($update), $this->getTable(), '`sensor_id`=?', array($this->sensor_id));
                echo 'U';
            }
        } else {
            $this->sensor_id = dbInsert($this->escapeNull($new_sensor), $this->getTable());
            if ($this->sensor_id !== null) {
                $name = static::$name;
                $message = "$name Discovered: {$this->type} {$this->subtype} {$this->index} {$this->description}";
                log_event($message, $this->device_id, static::$table, 3, $this->sensor_id);
                echo '+';
            }
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
        $table = $this->getTable();
        if (isset($this->sensor_id)) {
            return dbFetchRow(
                "SELECT `$table` FROM ? WHERE `sensor_id`=?",
                array($this->sensor_id)
            );
        }

        $sensor = dbFetchRow(
            "SELECT * FROM `$table` " .
            "WHERE `device_id`=? AND `sensor_class`=? AND `sensor_type`=? AND `sensor_index`=?",
            array($this->device_id, $this->type, $this->subtype, $this->index)
        );
        $this->sensor_id = $sensor['sensor_id'];
        return $sensor;
    }

    /**
     * Get the table for this sensor
     * @return string
     */
    public function getTable()
    {
        return static::$table;
    }

    /**
     * Get an array of this sensor with fields that line up with the database.
     * Excludes sensor_id and current
     *
     * @return array
     */
    protected function toArray()
    {
        return array(
            'sensor_class' => $this->type,
            'device_id' => $this->device_id,
            'sensor_oids' => json_encode($this->oids),
            'sensor_index' => $this->index,
            'sensor_type' => $this->subtype,
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
            'entPhysicalIndex_measured' => $this->entPhysicalMeasured,
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
     * Run Sensors discovery for the supplied OS (device)
     *
     * @param OS $os
     */
    public static function discover(OS $os)
    {
        // Add discovery types here
    }

    /**
     * Poll sensors for the supplied OS (device)
     *
     * @param OS $os
     */
    public static function poll(OS $os)
    {
        $table = static::$table;
        $sensors = dbFetchColumn(
            "SELECT `sensor_class` FROM `$table` WHERE `device_id` = ? GROUP BY `sensor_class`",
            array($os->getDeviceId())
        );

        foreach ($sensors as $type) {
            static::pollSensorType($os, $type);
        }
    }

    /**
     * Poll all sensors of a specific class
     *
     * @param OS $os
     * @param $type
     */
    protected static function pollSensorType($os, $type)
    {
        echo "$type:\n";

        $table = static::$table;
        $sensors = dbFetchRows(
            "SELECT * FROM `$table` WHERE `sensor_class` = ? AND `device_id` = ?",
            array($type, $os->getDeviceId())
        );

        $typeInterface = static::getPollingInterface($type);
        if (!interface_exists($typeInterface)) {
            echo "ERROR: Polling Interface doesn't exist! $typeInterface\n";
        }

        // fetch data
        if ($os instanceof $typeInterface) {
            d_echo("Using OS polling for $type\n");
            $function = static::getPollingMethod($type);
            $data = $os->$function($sensors);
        } else {
            $data = static::fetchSensorData($os->getDevice(), $sensors);
        }

        d_echo($data);

        // update data
        foreach ($sensors as $sensor) {
            $sensor_value = $data[$sensor['sensor_id']];

            echo "  {$sensor['sensor_descr']}: $sensor_value\n";

            // update rrd and database
            $rrd_name = array(
                static::$data_name,
                $sensor['sensor_class'],
                $sensor['sensor_type'],
                $sensor['sensor_index']
            );
            $rrd_def = RrdDefinition::make()->addDataset('sensor', 'GAUGE', -20000, 20000);

            $fields = array(
                'sensor' => isset($sensor_value) ? $sensor_value : 'U',
            );

            $tags = array(
                'sensor_class' => $sensor['sensor_class'],
                'sensor_type' => $sensor['sensor_type'],
                'sensor_descr' => $sensor['sensor_descr'],
                'sensor_index' => $sensor['sensor_index'],
                'rrd_name' => $rrd_name,
                'rrd_def' => $rrd_def
            );
            data_update($os->getDevice(), static::$data_name, $tags, $fields);

            $update = array(
                'sensor_prev' => $sensor['sensor_current'],
                'sensor_current' => $sensor_value,
                'lastupdate' => array('NOW()'),
            );
            dbUpdate($update, $table, "`sensor_id` = ?", array($sensor['sensor_id']));
        }
    }

    /**
     * Fetch data for the specified sensors
     * TODO: optimize
     *
     * @param $device
     * @param $sensors
     * @return array
     */
    protected static function fetchSensorData($device, $sensors)
    {
        $oids = self::prepSensorOids($sensors, get_device_oid_limit($device));

        $snmp_data = array();
        foreach ($oids as $oid_chunk) {
            $multi_data = snmp_get_multi_oid($device, $oid_chunk, '-OUQnt');
            $snmp_data = array_merge($snmp_data, $multi_data);
        }

        $sensor_data = array();
        foreach ($sensors as $sensor) {
            $requested_oids = array_flip(json_decode($sensor['sensor_oids']));
            $data = array_intersect_key($snmp_data, $requested_oids);

            // if no data set null and continue to the next sensor
            if (empty($data)) {
                $data[$sensor['sensor_id']] = null;
                continue;
            }

            if (count($data) > 1) {
                // aggregate data
                if ($sensor['sensor_aggregator'] == 'avg') {
                    $sensor_value = array_sum($data) / count($data);
                } else {
                    // sum
                    $sensor_value = array_sum($data);
                }
            } else {
                $sensor_value = current($data);
            }

            if ($sensor['sensor_divisor'] && $sensor_value !== 0) {
                $sensor_value = ($sensor_value / $sensor['sensor_divisor']);
            }

            if ($sensor['sensor_multiplier']) {
                $sensor_value = ($sensor_value * $sensor['sensor_multiplier']);
            }

            $sensor_data[$sensor['sensor_id']] = $sensor_value;
        }

        return $sensor_data;
    }


    /**
     * Get a list of unique oids from an array of sensors and break it into chunks.
     *
     * @param $sensors
     * @param int $chunk How many oids per chunk.  Default 10.
     * @return array
     */
    private static function prepSensorOids($sensors, $chunk = 10)
    {
        // Sort the incoming oids and sensors
        $oids = array_reduce($sensors, function ($carry, $sensor) {
            return array_merge($carry, json_decode($sensor['sensor_oids']));
        }, array());

        // only unique oids and chunk
        $oids = array_chunk(array_keys(array_flip($oids)), $chunk);

        return $oids;
    }

    protected static function discoverType(OS $os, $type)
    {
        echo "$type: ";

        $typeInterface = static::getDiscoveryInterface($type);
        if (!interface_exists($typeInterface)) {
            echo "ERROR: Discovery Interface doesn't exist! $typeInterface\n";
        }

        if ($os instanceof $typeInterface) {
            $function = static::getDiscoveryMethod($type);
            $sensors = $os->$function();
        } else {
            $sensors = array();  // TODO default implementation here or use Traits
        }

        static::sync($os->getDeviceId(), $type, $sensors);

        echo PHP_EOL;
    }

    protected static function getDiscoveryInterface($type)
    {
        return str_to_class($type, 'LibreNMS\\Interfaces\\Discovery\\Sensors\\') . 'Discovery';
    }

    protected static function getDiscoveryMethod($type)
    {
        return 'discover' . str_to_class($type);
    }

    protected static function getPollingInterface($type)
    {
        return str_to_class($type, 'LibreNMS\\Interfaces\\Polling\\Sensors\\') . 'Polling';
    }

    protected static function getPollingMethod($type)
    {
        return 'poll' . str_to_class($type);
    }

    /**
     * Is this sensor valid?
     * If not, it should not be added to or in the database
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->valid;
    }

    /**
     * Save sensors and remove invalid sensors
     * This the sensors array should contain all the sensors of a specific class
     * It may contain sensors from multiple tables and devices, but that isn't the primary use
     *
     * @param int $device_id
     * @param string $type
     * @param array $sensors
     */
    final public static function sync($device_id, $type, array $sensors)
    {
        // save and collect valid ids
        $valid_sensor_ids = array();
        foreach ($sensors as $sensor) {
            /** @var $this $sensor */
            if ($sensor->isValid()) {
                $valid_sensor_ids[] = $sensor->save();
            }
        }

        // delete invalid sensors
        self::clean($device_id, $type, $valid_sensor_ids);
    }

    /**
     * Remove invalid sensors.  Passing an empty array will remove all sensors of that class
     *
     * @param int $device_id
     * @param string $type
     * @param array $sensor_ids valid sensor ids
     */
    private static function clean($device_id, $type, $sensor_ids)
    {
        $table = static::$table;
        $params = array($device_id, $type);
        $where = '`device_id`=? AND `sensor_class`=? AND `sensor_id`';

        if (!empty($sensor_ids)) {
            $where .= ' NOT IN ' . dbGenPlaceholders(count($sensor_ids));
            $params = array_merge($params, $sensor_ids);
        }

        $delete = dbFetchRows("SELECT * FROM `$table` WHERE $where", $params);
        foreach ($delete as $sensor) {
            echo '-';

            $message = static::$name;
            $message .= " Deleted: $type {$sensor['sensor_type']} {$sensor['sensor_index']} {$sensor['sensor_descr']}";
            log_event($message, $device_id, static::$table, 3, $sensor['sensor_id']);
        }
        if (!empty($delete)) {
            dbDelete($table, $where, $params);
        }
    }
}
