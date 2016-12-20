<?php
/**
 * arp-table.php
 *
 * Collect arp table entries from devices and update the database
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
 * @copyright  2016 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

use Illuminate\Support\Collection;
use LibreNMS\SNMP;

if (key_exists('vrf_lite_cisco', $device) && (count($device['vrf_lite_cisco'])!=0)) {
    $vrfs_lite_cisco = $device['vrf_lite_cisco'];
} else {
    $vrfs_lite_cisco = array(array('context_name'=>''));
}

foreach ($vrfs_lite_cisco as $vrf) {
    $device['context_name'] = $vrf['context_name'];

    // collect data from device and database
    $arp_entries = SNMP::walk($device, array('ipNetToMediaPhysAddress', 'ipNetToPhysicalPhysAddress'), 'IP-MIB')
        ->map(function ($entry) use ($device) {
            return parse_arp_data($entry, $device);
        })->filter(null);

    $sql = "SELECT M.* from ipv4_mac AS M, ports AS I WHERE M.port_id=I.port_id AND I.device_id=? AND M.context_name=?";
    $params = array($device['device_id'], $device['context_name']);
    $existing_data = collect(dbFetchRows($sql, $params));


    // group data and update
    $live_ips = $arp_entries->pluck('ipv4_address');
    $arp_entries->groupBy(function ($entry) use ($existing_data) {
        // group up the entries returned from the device
        if ($existing_data->contains($entry)) {
            return 'Unchanged';
        } elseif ($existing_data->contains('ipv4_address', $entry['ipv4_address'])) {
            return 'Changed';
        }
        return 'New';
    })->put('Removed', $existing_data->reject(function ($entry) use ($live_ips) {
        // get a list of removed entries
        return $live_ips->contains($entry['ipv4_address']);
    }))->reject(function (Collection $removed) {
        // reject empty groups
        return $removed->isEmpty();
    })->each(function ($data, $group) use ($device, $existing_data) {
        // Update database
        print "$group: " . count($data) . PHP_EOL;
        update_arp_table($device, $group, $data, $existing_data);
    });

    unset($arp_entries, $existing_data, $removed_entries, $live_ips);
    unset($device['context_name']);
}
unset($vrfs_lite_cisco);
