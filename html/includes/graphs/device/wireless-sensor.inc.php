<?php
/**
 * wireless-sensor.inc.php
 *
 * Common file for Wireless Sensor Graphs
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

require 'includes/graphs/common.inc.php';

$num = '%5.1lf'; // default: float
if (isset($unit_type)) {
    if ($unit_type == 'int') {
        $num = '%4.0lf';
    } elseif ($unit_type == 'si') {
        $num .= '%s';
    }
}

$sensors = dbFetchRows(
    'SELECT * FROM `wireless_sensors` WHERE `sensor_class` = ? AND `device_id` = ? ORDER BY `sensor_index`',
    array($class, $device['device_id'])
);


if (count($sensors) == 1 && $unit_long == $sensors[0]['sensor_descr']) {
    $unit_long = '';
}

$col_w = 7 + strlen($unit);
$rrd_options .= " COMMENT:'". str_pad($unit_long, 19) . str_pad("Cur", $col_w). str_pad("Min", $col_w) . "Max\\n'";

foreach ($sensors as $index => $sensor) {
    // FIXME generic colour function
    switch ($index % 7) {
        case 0:
            $colour = 'CC0000';
            break;

        case 1:
            $colour = '008C00';
            break;

        case 2:
            $colour = '4096EE';
            break;

        case 3:
            $colour = '73880A';
            break;

        case 4:
            $colour = 'D01F3C';
            break;

        case 5:
            $colour = '36393D';
            break;

        case 6:
        default:
            $colour = 'FF0084';
    }//end switch


    $sensor_descr_fixed = rrdtool_escape($sensor['sensor_descr'], 12);
    $rrd_file = rrd_name($device['hostname'], array('wireless-sensor', $sensor['sensor_class'], $sensor['sensor_type'], $sensor['sensor_index']));
    $rrd_options .= " DEF:sensor{$sensor['sensor_id']}=$rrd_file:sensor:AVERAGE";
    $rrd_options .= " LINE1:sensor{$sensor['sensor_id']}#$colour:'$sensor_descr_fixed'";
    $rrd_options .= " GPRINT:sensor{$sensor['sensor_id']}:LAST:$num$unit";
    $rrd_options .= " GPRINT:sensor{$sensor['sensor_id']}:MIN:$num$unit";
    $rrd_options .= " GPRINT:sensor{$sensor['sensor_id']}:MAX:$num$unit\\l ";
    $iter++;
}//end foreach
