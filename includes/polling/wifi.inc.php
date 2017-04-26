<?php

use LibreNMS\RRD\RrdDefinition;

if ($device['type'] == 'network' || $device['type'] == 'firewall' || $device['type'] == 'wireless') {
    if ($device['os'] == 'airos') {
        echo 'It Is Airos' . PHP_EOL;
        include 'includes/polling/mib/ubnt-airmax-mib.inc.php';
    } elseif ($device['os'] == 'airos-af') {
        echo 'It Is AirFIBER' . PHP_EOL;
        include 'includes/polling/mib/ubnt-airfiber-mib.inc.php';
    } elseif ($device['os'] == 'ceraos') {
        echo 'It is Ceragon CeroOS' . PHP_EOL;
        include 'includes/polling/mib/ceraos-mib.inc.php';
    } elseif ($device['os'] == 'siklu') {
        echo 'It is Siklu' . PHP_EOL;
        include 'includes/polling/mib/siklu-mib.inc.php';
    } elseif ($device['os'] == 'saf') {
        echo 'It is SAF Tehnika' . PHP_EOL;
        include 'includes/polling/mib/saf-mib.inc.php';
    } elseif ($device['os'] == 'sub10') {
        echo 'It is Sub10' . PHP_EOL;
        include 'includes/polling/mib/sub10-mib.inc.php';
    } elseif ($device['os'] == 'airport') {
        // # GENERIC FRAMEWORK, FILLING VARIABLES
        echo 'Checking Airport Wireless clients... ';

        $wificlients1 = (snmp_get($device, 'wirelessNumber.0', '-OUqnv', 'AIRPORT-BASESTATION-3-MIB') + 0);

        echo $wificlients1." clients\n";
    } elseif ($device['os'] == 'symbol' && str_contains($device['hardware'], 'AP', true)) {
        echo 'Checking Symbol Wireless clients... ';

        $wificlients1 = snmp_get($device, '.1.3.6.1.4.1.388.11.2.4.2.100.10.1.18.1', '-Ovq', '""');

        echo (($wificlients1 + 0).' clients on wireless connector, ');
    } elseif ($device['os'] == 'unifi') {
        include 'includes/polling/mib/ubnt-unifi-mib.inc.php';
    } elseif ($device['os'] == 'deliberant' && str_contains($device['hardware'], "DLB APC Button")) {
        echo 'Checking Deliberant APC Button wireless clients... ';
        $wificlients1 = snmp_get($device, '.1.3.6.1.4.1.32761.3.5.1.2.1.1.16.7', '-OUqnv');
        echo $wificlients1." clients\n";
    } elseif ($device['os'] == 'deliberant' && $device['hardware'] == "\"DLB APC 2Mi\"") {
        echo 'Checking Deliberant APC 2Mi wireless clients... ';
        $wificlients1 = snmp_get($device, '.1.3.6.1.4.1.32761.3.5.1.2.1.1.16.5', '-OUqnv');
        echo $wificlients1." clients\n";
    }

    // Loop through all $wificlients# and data_update()
    $i = 1;
    while (is_numeric(${'wificlients'.$i})) {
        $tags = array(
            'rrd_def'   => RrdDefinition::make()->addDataset('wificlients', 'GAUGE', -273, 1000),
            'rrd_name'  => array('wificlients', "radio$i"),
            'radio'     => $i,
        );
        data_update($device, 'wificlients', $tags, ${'wificlients'.$i});
        $graphs['wifi_clients'] = true;
        unset(${'wificlients'.$i});
        $i++;
    }
    unset($i);
} else {
    echo 'Unsupported type: ' . $device['type'] . PHP_EOL;
}
