<?php
/**
 * ios.inc.php
 *
 * Discover client counts for Aironet and other IOS devices
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

if (starts_with($device['hardware'], 'AIR-') || str_contains($device['hardware'], 'ciscoAIR')) {
    $data = snmpwalk_cache_oid($device, 'cDot11ActiveWirelessClients', array(), 'CISCO-DOT11-ASSOCIATION-MIB');
    $entPhys = snmpwalk_cache_oid($device, 'entPhysicalDescr', array(), 'ENTITY-MIB');

    // fixup incorrect/missing entPhysicalIndex mapping
    foreach ($data as $index => $_unused) {
        foreach ($entPhys as $entIndex => $ent) {
            $descr = $ent['entPhysicalDescr'];
            unset($entPhys[$entIndex]); // only use each one once

            if (ends_with($descr, 'Radio')) {
                d_echo("Mapping entPhysicalIndex $entIndex to ifIndex $index\n");
                $data[$index]['entPhysicalIndex'] = $entIndex;
                $data[$index]['entPhysicalDescr'] = $descr;
                break;
            }
        }
    }

    foreach ($data as $index => $entry) {
        discover_wireless_sensor($valid, 'clients', $device, ".1.3.6.1.4.1.9.9.273.1.1.2.1.1.$index", $index, 'ios',
            $entry['entPhysicalDescr'], $entry['cDot11ActiveWirelessClients'], null, 1, 1, 'sum', 0, 0, 30, 40,
            $entry['entPhysicalIndex'], 'ports');
    }
}
