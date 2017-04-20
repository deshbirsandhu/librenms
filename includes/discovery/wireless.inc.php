<?php
/**
 * wireless.inc.php
 *
 * -Description-
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

$vars = $GLOBALS;

$wireless_sensors = array(
    'clients',
);

foreach ($wireless_sensors as $sensor_class) {
    echo ucfirst($sensor_class) . ": {$device['os']} ";

    include "includes/discovery/wireless/$sensor_class/{$device['os']}.inc.php";

    $entries = dbFetchRows(
        'SELECT * FROM `wireless_sensors` WHERE `sensor_class`=? AND `device_id`=?',
        array($sensor_class, $device['device_id'])
    );

    foreach ($entries as $entry) {
        $sensor_id = $entry['sensor_id'];

        if (!in_array($sensor_id, $valid['wireless'][$sensor_class])) {
            echo '-';
        dbDelete('wireless_sensors', '`sensor_id` =  ?', array($sensor_id));
            log_event('Wireless Sensor Deleted: ' . $entry['sensor_class'] . ' ' . $entry['sensor_type'] . ' ' . $entry['sensor_index'] . ' ' . $entry['sensor_descr'], $device, 'sensor', 3, $sensor_id);
        }
    }

    echo PHP_EOL;
}

unset($wireless_sensors, $sensor_class, $entries);
