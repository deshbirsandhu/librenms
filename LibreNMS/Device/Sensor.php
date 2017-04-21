<?php
/**
 * Created by PhpStorm.
 * User: murrant
 * Date: 4/21/17
 * Time: 6:27 AM
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
        $entPhysicalIndex = null,
        $entPhysicalReference = null,
        $high_limit = null,
        $low_limit = null,
        $high_warn = null,
        $low_warn = null
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
                dbUpdate($this->table, 'WHERE `sensor_id`=?', $this->sensor_id);
                echo 'U';
            }
        } else {
            $this->sensor_id = dbInsert($new_sensor, $this->table);
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
                'SELECT * FROM ? WHERE `sensor_id`=?',
                array($this->table, $this->sensor_id)
            );
        }

        $sensor = dbFetchRow(
            'SELECT * FROM ? WHERE `device_id`=? AND `sensor_class`=? AND `sensor_type`=? AND `sensor_index`=?',
            array($this->table, $this->device_id, $this->class, $this->type, $this->index)
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
}
