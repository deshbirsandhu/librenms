<?php
/**
 * unifi.inc.php
 *
 * Discover client counts for unifi devices
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

$client_oids = snmpwalk_cache_oid($device, 'UBNT-UniFi-MIB::unifiVapRadio', array());
$client_oids = snmpwalk_cache_oid($device, 'UBNT-UniFi-MIB::unifiVapNumStations', $client_oids);

$radios = array();
foreach ($client_oids as $index => $entry) {
    $radio_name = $entry['unifiVapRadio'];
    $radios[$radio_name]['oids'][] = '.1.3.6.1.4.1.41112.1.6.1.2.1.8.' . $index;
    $radios[$radio_name]['count'] += $entry['unifiVapNumStations'];
}

foreach ($radios as $index => $data) {
    $descr = strtoupper($index) . ' Radio';
    discover_wireless_sensor($valid, 'clients', $device, $data['oids'], $index, 'unifi', $descr, $data['count'],
        null, 1, 1, 'sum', null, null, 30, 40);
}
