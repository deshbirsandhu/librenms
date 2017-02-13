<?php
/**
 * NetSnmp.php
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

namespace LibreNMS\SNMP\Engines;

use LibreNMS\Proc;
use LibreNMS\SNMP;
use LibreNMS\SNMP\Cache;
use LibreNMS\SNMP\Contracts\SnmpTranslator;
use LibreNMS\SNMP\Format;

class NetSnmp extends RawBase implements SnmpTranslator
{
    /**
     * @param array $device
     * @param string|array $oids single or array of oids to walk
     * @param null $options Options to sent to snmpget
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return string exact results from snmpget
     */
    public function getRaw($device, $oids, $options = null, $mib = null, $mib_dir = null)
    {
        if (empty($oids)) {
            return '';
        }

        $oids = is_array($oids) ? implode(' ', $oids) : $oids;
        return $this->exec($this->genSnmpgetCmd($device, $oids, $options, $mib, $mib_dir));
    }

    /**
     * @param array $device
     * @param string $oid single oid to walk
     * @param string $options Options to send to snmpwalk
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return string exact results from snmpwalk
     */
    public function walkRaw($device, $oid, $options = null, $mib = null, $mib_dir = null)
    {
        return $this->exec($this->genSnmpwalkCmd($device, $oid, $options, $mib, $mib_dir));
    }

    /**
     * @param array $device
     * @param string|array $oids
     * @param string $options
     * @param string $mib
     * @param string $mib_dir
     * @return string
     * @internal param string $oid
     */
    public function translate($device, $oids, $options = null, $mib = null, $mib_dir = null)
    {
//        $oid_cache_keys = $this->getCacheKeys($oids, 'NetSnmp::translate', $device);

        // retrieve cached data
//        $cached = Cache::multiGet($oid_cache_keys);
        // TODO use cache

        $data = collect($oids);
        $cmd  = 'snmptranslate '.$this->getMibDir($mib_dir, $device);
        if (isset($mib)) {
            $cmd .= " -m $mib";
        }
        $cmd .= " $options ";
        $cmd .= $data->implode(' ');
        $cmd .= ' 2>/dev/null';  // don't allow errors to throw an exception

        $output = collect(explode("\n\n", $this->exec($cmd)));

        $result = $data->combine(array_pad($output->all(), $data->count(), null));

        return is_array($oids) ? $result->all() : $result->first();
    }

    /**
     * @param array $device
     * @param string|array $oids
     * @param string $mib
     * @param string $mib_dir
     * @return string|array
     * @throws \Exception
     */
    public function translateNumeric($device, $oids, $mib = null, $mib_dir = null)
    {
        // FIXME is this necessary?
        $formmatted_oids = collect($oids)->combine($oids)->map(function ($oid) use ($mib) {
            return Format::compoundOid($oid, $mib);
        });

        $numeric = $formmatted_oids->filter(function ($oid) {
            return Format::isNumericOid($oid);
        });

        $cache_keys = $this->getCacheKeys($formmatted_oids, 'NetSnmp::translateNumeric', $device);
        $cached = Cache::multiGet($cache_keys);

        $result = $numeric->merge($cached);

        $oids_to_translate = $formmatted_oids->diffKeys($result)->values();

        $translated = SNMP::translate($device, $oids_to_translate->all(), '-IR -On', $mib, $mib_dir);

        $cache_keys->union($oids_to_translate->combine($translated))->each(function ($data, $key) {
            Cache::put($key, $data);
        });

        $result = $formmatted_oids->combine($result->merge($translated)->all());

        return is_array($oids) ? $result->all() : $result->first();
    }

    private static function exec($cmd)
    {
        global $debug;
        c_echo("SNMP[%c$cmd%n]\n", $debug);
        $process = new Proc($cmd, null, null, true);
        list($output, $stderr) = $process->getOutput();
        $process->close();

        $output = rtrim($output);
        d_echo("[$output]\n");

        if (!empty($stderr)) {
            throw new \SNMPException("[$cmd]\n$stderr");
        }

        return $output;
    }

    /**
     * Generate the mib search directory argument for snmpcmd
     * If null return the default mib dir
     * If $mibdir is empty '', return an empty string
     *
     * @param string $mibdir should be the name of the directory within $config['mib_dir']
     * @param array $device
     * @return string The option string starting with -M
     */
    private function getMibDir($mibdir = null, $device = array())
    {
        global $config;

        // get mib directories from the device
        $extra_dir = '';
        if (file_exists($config['mib_dir'] . '/' . $device['os'])) {
            $extra_dir .= $config['mib_dir'] . '/' . $device['os'] . ':';
        }

        if (isset($device['os_group']) && file_exists($config['mib_dir'] . '/' . $device['os_group'])) {
            $extra_dir .= $config['mib_dir'] . '/' . $device['os_group'] . ':';
        }

        if (isset($config['os_groups'][$device['os_group']]['mib_dir'])) {
            if (is_array($config['os_groups'][$device['os_group']]['mib_dir'])) {
                foreach ($config['os_groups'][$device['os_group']]['mib_dir'] as $k => $dir) {
                    $extra_dir .= $config['mib_dir'] . '/' . $dir . ':';
                }
            }
        }

        if (isset($config['os'][$device['os']]['mib_dir'])) {
            if (is_array($config['os'][$device['os']]['mib_dir'])) {
                foreach ($config['os'][$device['os']]['mib_dir'] as $k => $dir) {
                    $extra_dir .= $config['mib_dir'] . '/' . $dir . ':';
                }
            }
        }

        if (is_null($mibdir)) {
            return " -M $extra_dir${config['mib_dir']}";
        }

        if (empty($mibdir)) {
            return '';
        }

        // automatically set up includes
        return " -M $extra_dir{$config['mib_dir']}/$mibdir:{$config['mib_dir']}";
    }

    /**
     * Generate an snmpget command
     *
     * @param array $device the we will be connecting to
     * @param string $oids the oids to fetch, separated by spaces
     * @param string $options extra snmp command options, usually this is output options
     * @param string $mib an additional mib to add to this command
     * @param string $mibdir a mib directory to search for mibs, usually prepended with +
     * @return string the fully assembled command, ready to run
     */
    private function genSnmpgetCmd($device, $oids, $options = null, $mib = null, $mibdir = null)
    {
        global $config;
        $snmpcmd  = $config['snmpget'];
        return self::genSnmpCmd($snmpcmd, $device, $oids, $options, $mib, $mibdir);
    }

    /**
     * Generate an snmpwalk command
     *
     * @param array $device the we will be connecting to
     * @param string $oids the oids to fetch, separated by spaces
     * @param string $options extra snmp command options, usually this is output options
     * @param string $mib an additional mib to add to this command
     * @param string $mibdir a mib directory to search for mibs, usually prepended with +
     * @return string the fully assembled command, ready to run
     */
    private function genSnmpwalkCmd($device, $oids, $options = null, $mib = null, $mibdir = null)
    {
        global $config;
        if ($device['snmpver'] == 'v1' ||
            (isset($device['os'], $config['os'][$device['os']]['nobulk']) &&
            $config['os'][$device['os']]['nobulk'])
        ) {
            $snmpcmd = $config['snmpwalk'];
        } else {
            $snmpcmd = $config['snmpbulkwalk'];
            $max_repeaters = self::getMaxRepeaters($device);
            if ($max_repeaters > 0) {
                $snmpcmd .= " -Cr$max_repeaters ";
            }
        }
        return self::genSnmpCmd($snmpcmd, $device, $oids, $options, $mib, $mibdir);
    }

    /**
     * Generate an snmp command
     *
     * @param string $type either 'get' or 'walk'
     * @param array $device the we will be connecting to
     * @param string $oids the oids to fetch, separated by spaces
     * @param string $options extra snmp command options, usually this is output options
     * @param string $mib an additional mib to add to this command
     * @param string $mibdir a mib directory to search for mibs, usually prepended with +
     * @return string the fully assembled command, ready to run
     */
    private function genSnmpCmd($cmd, $device, $oids, $options = null, $mib = null, $mibdir = null)
    {
        // populate timeout & retries values from configuration
        $timeout = self::prepSetting($device, 'timeout');
        $retries = self::prepSetting($device, 'retries');

        if (!isset($device['transport'])) {
            $device['transport'] = 'udp';
        }

        $cmd .= self::genAuth($device);
        $cmd .= " $options";
        $cmd .= $mib ? " -m $mib" : '';
        $cmd .= self::getMibDir($mibdir, $device);
        $cmd .= isset($timeout) ? " -t $timeout" : '';
        $cmd .= (isset($retries) && is_numeric($retries))? " -r $retries" : '';
        $cmd .= ' ' . $device['transport'] . ':' . $device['hostname'] . ':' . $device['port'];
        $cmd .= " $oids";

        return $cmd;
    }

    private function getMaxRepeaters($device)
    {
        global $config;

        $max_repeaters = $device['snmp_max_repeaters'];

        if (isset($max_repeaters) && $max_repeaters > 0) {
            return $max_repeaters;
        } elseif (isset($config['snmp']['max_repeaters']) && $config['snmp']['max_repeaters'] > 0) {
            return $config['snmp']['max_repeaters'];
        } else {
            return false;
        }
    }

    private function genAuth($device)
    {
        global $debug;

        $cmd = '';

        if ($device['snmpver'] === 'v3') {
            $cmd = " -v3 -n '' -l '".$device['authlevel']."'";

            //add context if exist context
            if (key_exists('context_name', $device)) {
                $cmd = " -v3 -n '".$device['context_name']."' -l '".$device['authlevel']."'";
            }

            if ($device['authlevel'] === 'noAuthNoPriv') {
                // We have to provide a username anyway (see Net-SNMP doc)
                $username = !empty($device['authname']) ? $device['authname'] : 'root';
                $cmd .= " -u '".$username."'";
            } elseif ($device['authlevel'] === 'authNoPriv') {
                $cmd .= " -a '".$device['authalgo']."'";
                $cmd .= " -A '".$device['authpass']."'";
                $cmd .= " -u '".$device['authname']."'";
            } elseif ($device['authlevel'] === 'authPriv') {
                $cmd .= " -a '".$device['authalgo']."'";
                $cmd .= " -A '".$device['authpass']."'";
                $cmd .= " -u '".$device['authname']."'";
                $cmd .= " -x '".$device['cryptoalgo']."'";
                $cmd .= " -X '".$device['cryptopass']."'";
            } else {
                if ($debug) {
                    print 'DEBUG: '.$device['snmpver']." : Unsupported SNMPv3 AuthLevel (wtf have you done ?)\n";
                }
            }
        } elseif ($device['snmpver'] === 'v2c' or $device['snmpver'] === 'v1') {
            $cmd  = " -".$device['snmpver'];
            $cmd .= " -c '".$device['community']."'";
        } else {
            if ($debug) {
                print 'DEBUG: '.$device['snmpver']." : Unsupported SNMP Version (shouldn't be possible to get here)\n";
            }
        }//end if

        return $cmd;
    }
}
