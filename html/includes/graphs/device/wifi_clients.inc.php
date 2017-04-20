<?php

$colours = 'mixed';
$unit_text = 'Clients';
$scale_min = '0';


$client_sensors = dbFetchRows('SELECT * FROM `wireless_sensors` WHERE `device_id`=? AND `sensor_class`=?', array($device['device_id'], 'clients'));

$rrd_list = array();
foreach ($client_sensors as $sensor) {
    $rrd_name = rrd_name(
        $device['hostname'],
        array('wireless-sensor', $sensor['sensor_class'], $sensor['sensor_type'], $sensor['sensor_index'])
    );

    $rrd_list[] = array(
        'filename' => $rrd_name,
        'ds' => 'wireless-sensor',
        'descr' => $sensor['sensor_descr'],
    );
}

// fall back to old wificlients
if (empty($rrd_list)) {
    $i = 1;
    $rrd_filename = rrd_name($device['hostname'], "wificlients-radio$i");
    while (rrdtool_check_rrd_exists($rrd_filename)) {
        $rrd_list[$i] =
            array(
                'filename' => $rrd_filename,
                'ds' => 'wificlients',
                'descr' => "Radio$i Clients",
            );
        $i++;
    };
}

require 'includes/graphs/generic_multi_line.inc.php';
