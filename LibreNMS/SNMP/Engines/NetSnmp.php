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

use LibreNMS\Cache;
use LibreNMS\Config;
use LibreNMS\Proc;
use LibreNMS\SNMP;
use LibreNMS\SNMP\Contracts\SnmpTranslator;
use LibreNMS\SNMP\Format;

class NetSnmp extends RawBase implements SnmpTranslator
{
    /**
     * @param array $device
     * @param string|array $oids single or array of oids to walk
     * @param null $options OptionsR to sent to snmpget
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
//        $oid_cache_keys = $this->getCacheKeys($oids, 'NetSnmp::translate', $device, $options);

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
        // Format oids into Mib::oid format.
        $formatted_oids = collect($oids)->combine($oids)->map(function ($oid) use ($mib) {
            return Format::compoundOid($oid, $mib);
        });

        // get the OIDs that are already numeric
        $numeric = $formatted_oids->filter(function ($oid) {
            return Format::isNumericOid($oid);
        });

        // get what we can from the cache
        $cache_keys = $this->getCacheKeys($formatted_oids, 'NetSnmp::translateNumeric', $device);
        $cached = Cache::multiGet($cache_keys);

        // merge numeric and cached results
        $result = $numeric->merge($cached);

        // get the oids that are yet to be translated
        $oids_to_translate = $formatted_oids->diffKeys($result)->values();

        $translated = SNMP::translate($device, $oids_to_translate->all(), '-IR -On', $mib, $mib_dir);

        // save the translated oids to the cache for one week
        $cache_keys->union($oids_to_translate->combine($translated))->each(function ($data, $key) {
            Cache::put($key, $data, 604800);
        });

        // collect all of the results
        $result = $formatted_oids->combine($result->merge($translated)->all());

        // only return an array if the requested oids was an array
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
     * @param string $mibdir should be the name of the directory within the LibreNMS mib directory
     * @param array $device
     * @return string The option string starting with -M
     */
    private function getMibDir($mibdir = null, $device = array())
    {
        $base_mibdir = Config::get('mib_dir') . '/';

        $dirs = collect()
            ->push($device['os'])
            ->push($device['os_group'])
            ->merge(Config::get("os_groups.{$device['os_group']}.mib_dir"))
            ->merge(Config::get("os.{$device['os']}.mib_dir"))
            ->push($mibdir)
            ->map(function ($dir) use ($base_mibdir) {
                return $base_mibdir . $dir;
            })
            ->push($base_mibdir)
            ->unique()
            ->filter(function ($dir) {
                return is_dir($dir);
            })->implode(':');

        return " -M $dirs";
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
        return self::genSnmpCmd(Config::get('snmpget'), $device, $oids, $options, $mib, $mibdir);
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
        if ($device['snmpver'] == 'v1' || Config::get("os.{$device['os']}.nobulk")) {
            $snmpcmd = Config::get('snmpwalk');
        } else {
            $snmpcmd = Config::get('snmpbulkwalk');
            $snmpcmd .= self::getMaxRepeaters($device);
        }
        return self::genSnmpCmd($snmpcmd, $device, $oids, $options, $mib, $mibdir);
    }

    /**
     * Generate an snmp command
     *
     * @param string $cmd either 'snmpget' or 'snmpwalk'
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
        $cmd .= isset($retries) ? " -r $retries" : '';
        $cmd .= ' ' . $device['transport'] . ':' . $device['hostname'] . ':' . $device['port'];
        $cmd .= " $oids";

        return $cmd;
    }

    /**
     * @param array $device
     * @return string example " -Cr1"
     */
    private function getMaxRepeaters($device)
    {
        $max_repeaters = $device['snmp_max_repeaters'];
        if ($max_repeaters < 1) {
            $max_repeaters = Config::get('snmp.max_repeaters');
        }

        if ($max_repeaters < 1) {
            return '';
        }

        return  " -Cr$max_repeaters";
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
