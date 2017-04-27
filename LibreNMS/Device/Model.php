<?php
/**
 * Model.php
 *
 * Base Model class
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

abstract class Model
{
    protected static $table;
    protected static $id_column;
    protected static $unique_columns = array();
    protected static $columns;
    protected static $excluded_update_columns = array();


    abstract public function isValid();

    /**
     * Get the table for this sensor
     * @return string
     */
    public function getTable()
    {
        return static::$table;
    }


    final public static function sync(array $models, array $fields)
    {
        // save and collect valid ids
        $valid_ids = array();
        foreach ($models as $model) {
            /** @var $this $model */
            if ($model->isValid()) {
                $valid_ids[] = $model->save();
            }
        }

        // delete invalid sensors
        self::clean($valid_ids, $fields);
    }

    /**
     * Save this sensor to the database.
     *
     * @return int the sensor_id of this sensor in the database
     */
    public function save()
    {
        $id = static::$id_column;
        $db_entry = $this->fetch();


        if ($db_entry) {
            $update = array_diff_assoc($this->toUpdateArray(), $db_entry);


            if (empty($update)) {
                echo '.';
            } else {
                dbUpdate($this->escapeNull($update), $this->getTable(), "`$id`=?", array($this->$id));
                echo 'U';
            }
        } else {
            $new_entry = $this->escapeNull($this->toArray());
            unset($new_entry[$id]); // remove id field
            $this->$id = dbInsert($new_entry, $this->getTable());
            if ($this->$id !== null) {
                echo '+';
            }
        }

        return $this->$id;
    }

    final protected function fetch()
    {
        $table = $this->getTable();
        $id = static::$id_column;
        if (isset($this->$id)) {
            return dbFetchRow(
                "SELECT * FROM `$table` WHERE `$id`=?",
                array($this->$id)
            );
        }

        if (empty(static::$unique_columns)) {
            return null;
        }

        list($where, $params) = $this->buildWhere($this->arrayOfProperties(static::$unique_columns));
        $record = dbFetchRow("SELECT * FROM `$table` WHERE " . $where, $params);
        $this->$id = $record[$id];
        return $record;
    }

    private static function buildWhere(array $fields)
    {
        $where = '';
        $columns = array();
        $params = array();
        foreach ($fields as $column => $value) {
            $columns[] = "`$column`";
            $params[] = $value;
        }
        if (!empty($columns)) {
            $where .= implode('=? AND ', $columns) . '=?';
        }
        return array($where, $params);
    }

    private static function clean(array $ids, array $fields)
    {
        $table = static::$table;
        $id = static::$id_column;

        list($where, $params) = self::buildWhere($fields);

        if (!empty($ids)) {
            $where .= "`$id` NOT IN " . dbGenPlaceholders(count($ids));
            $params = array_merge($params, $ids);
        }

        if (!empty($delete)) {
            $deleted = dbDelete($table, $where, $params);
            echo str_repeat('-', $deleted);
        }
    }


    private function toArray()
    {
        return $this->arrayOfProperties(static::$columns);
    }

    private function toUpdateArray()
    {
        $columns = array_diff(static::$columns, static::$excluded_update_columns, array(static::$id_column));
        return $this->arrayOfProperties($columns);
    }

    private function arrayOfProperties($properties)
    {
        $array = array();
        foreach ($properties as $property) {
//            echo $property . '->' . $this->$property . PHP_EOL;
            $array[$property] = $this->$property;
        }
        return $array;
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
}
