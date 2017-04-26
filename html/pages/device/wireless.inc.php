<?php

$type_text = array(
    'overview'    => 'Overview',
    'clients'     => 'Clients',
    'ccq'         => 'CCQ',
    'noise-floor' => 'Noise Floor',
);

$sensors = dbFetchColumn(
    "SELECT `sensor_class` FROM `wireless_sensors` WHERE `device_id` = ? GROUP BY `sensor_class`",
    array($device['device_id'])
);
$sensors[] = 'overview';
$datas = array_intersect(array_keys($type_text), $sensors);


$link_array = array(
    'page'   => 'device',
    'device' => $device['device_id'],
    'tab'    => 'wireless',
);

print_optionbar_start();

echo "<span style='font-weight: bold;'>Wireless</span> &#187; ";

if (!$vars['metric']) {
    $vars['metric'] = 'overview';
}

$sep = '';
foreach ($datas as $type) {
    echo $sep;
    $sep = ' | ';
    if ($vars['metric'] == $type) {
        echo '<span class="pagemenu-selected">';
    }

    echo generate_link($type_text[$type], $link_array, array('metric' => $type));

    if ($vars['metric'] == $type) {
        echo '</span>';
    }


}

print_optionbar_end();

if (is_file('pages/device/wireless/'.mres($vars['metric']).'.inc.php')) {
    include 'pages/device/wireless/'.mres($vars['metric']).'.inc.php';
} else {
    foreach ($datas as $type) {
        if ($type != 'overview') {
            $graph_title         = $type_text[$type];
            $graph_array['type'] = 'device_'.$type;

            include 'includes/print-device-graph.php';
        }
    }
}

$pagetitle[] = 'Wireless';
