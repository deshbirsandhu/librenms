<?php
/**
 * ping.php
 *
 * ping devices with high frequency
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

//$output = 'amorbis                        : 0.02
//havelock.rtr.ncn.net           : 84.33
//overmind.murray.lan            : 0.11
//palmer.rtr.ncn.net             : 78.14
//pioneer.sw.ncn.net             : -
//router.asus.com                : 0.24
//saturn                         : -
//snmpsim                        : 0.02
//tech-ts.exchange.ncn.net       : 55.64
//temp-celltower.devices.ncn.net : -
//truesdale.rtr.ncn.net          : 82.09
//wbcore.rtr.ncn.net             : 78.32
//';
//
//preg_match_all('/(?<alive>.+?) +: (?<ping>[\.0-9]+)|(?<unreachable>.+?) +: -/', $output, $matches);
//var_dump($matches);
//exit;


$time_start = microtime(true);
$count = 0;

$options = getopt('h:d');

$init_modules = array();
require __DIR__ . '/includes/init.php';

if ($config['icmp_check'] !== true) {
    echo 'Ping disabled, see $config[\'icmp_check\']';
    exit;
}

if ($config['noinfluxdb'] !== true && $config['influxdb']['enable'] === true) {
    $influxdb = influxdb_connect();
} else {
    $influxdb = false;
}

if (isset($options['d'])) {
    $debug=true;
}

$offset = 0;
$limit = $config['max_pings'];
$step = $config['ping_rrd_step'];

$sql = 'SELECT `devices`.`device_id`, `devices`.`hostname` FROM `devices`
 LEFT JOIN `devices_attribs` ON `devices`.`device_id`=`devices_attribs`.`device_id`
  AND `devices_attribs`.`attrib_type`="override_icmp_disable"
   WHERE `devices_attribs`.`attrib_value` IS NULL
    OR `devices_attribs`.`attrib_value` != "true"
    LIMIT ?, ?';

$tags = array(
    'rrd_def' => "DS:ping:GAUGE:$step:0:65535",
    'rrd_step' => $step
);

/**
 * Ping a group of hosts.
 *
 * Will return an array with the following keys
 * alive - hosts as the key, ping time as the value
 * unreachable - array of unreachable hosts
 *
 * @param array $hosts Array of hostnames/IPs to ping
 * @return array
 */
function pingHosts($hosts)
{
    global $config;
    $cmd = $config['fping'];
    $cmd .= ' -C 1 -q ';
    $cmd .= implode(' ', $hosts);
    $cmd .= ' 2>&1 >/dev/null';
    $output = shell_exec($cmd);

    $regex = '/(?<alive>.+?) +: (?<ping>[\.0-9]+)|(?<unreachable>.+?) +: -/';

    preg_match_all($regex, $output, $matches);

    // remove empty results
    $pings = array_combine($matches['alive'], $matches['ping']);
    $result = array(
        'alive' => array_filter($pings),
        'unreachable' => array_values(array_filter($matches['unreachable']))
    );

    return $result;
}

while($devices = dbFetchRows($sql, array(array($offset), array($limit)))) {
    $hosts = array_column($devices, 'hostname');
    $devices = array_combine($hosts, array_column($devices, 'device_id'));
    d_echo($devices);

    $results = pingHosts($hosts);
    d_echo($results);

    $update = array();
    foreach ($results['alive'] as $host => $ping) {
        // TODO account for down snmp devices
        $update = array(
            'status' => 1,
            'last_ping' => array('NOW()'),
            'last_ping_timetaken' => $ping
        );
        dbUpdate($update, 'devices', '`device_id`=?', array($devices[$host]));
        $fields = array('ping' => $ping);
        data_update(array('hostname' => $host), 'ping-perf', $tags, $fields);
    }

    foreach ($results['unreachable'] as $host) {
        $update = array(
            'status' => 0,
            'status_reason' => 'icmp'
        );
        dbUpdate($update, 'devices', '`device_id`=?', array($devices[$host]));
    }

    $count += count($hosts);
    $offset += $limit;
}

printf("Pinged %d hosts in %.3f seconds.\n", $count, microtime(true) - $time_start);

