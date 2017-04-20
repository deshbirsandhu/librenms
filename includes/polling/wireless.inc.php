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

$sensors = dbFetchColumn("SELECT `sensor_class` FROM `wireless_sensors` WHERE `device_id` = ? GROUP BY `sensor_class`", array($device['device_id']));
foreach ($sensors as $sensor_class) {
    poll_wireless_sensor($device, $sensor_class);

    if ($sensor_class == 'clients') {
        $graphs['wifi_clients'] = true;
    }
}

unset($sensors, $sensor_class);
