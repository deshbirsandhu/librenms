<?php
/**
 * SNMP.php
 *
 * SNMP Wrapper class
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

namespace LibreNMS;

use LibreNMS\Exceptions\InvalidOidFormatException;
use LibreNMS\SNMP\Contracts\SnmpEngine;
use LibreNMS\SNMP\Contracts\SnmpTranslator;
use LibreNMS\SNMP\DataSet;
use LibreNMS\SNMP\Engines\NetSnmp;
use LibreNMS\SNMP\OIDData;

class SNMP
{
    const ERROR_NONE = 0;
    const ERROR_UNREACHABLE = 1;
    const ERROR_NO_SUCH_OID = 2;
    const ERROR_PARSE_ERROR = 4;

    /** @var SnmpEngine */
    private static $engine;
    private static $translator;

    /**
     * Get the SnmpEngine instance.  This is called automatically by \LibreNMS\SNMP.
     * NetSNMP is currently the default implementation.
     * The private instance will be set to any passed engine,
     * discarding the old engine and any data it might have cached.
     *
     * @param SnmpEngine|null $engine
     * @return SnmpEngine|NetSnmp
     */
    public static function getInstance(SnmpEngine $engine = null)
    {
        if ($engine !== null) {
            self::$engine = $engine;
        }

        if (self::$engine === null) {
            self::$engine = new NetSNMP(); // default engine
        }

        return self::$engine;
    }

    /**
     * @param SnmpTranslator|null $translator
     * @return SnmpTranslator
     */
    public static function getTranslator(SnmpTranslator $translator = null)
    {
        if ($translator !== null) {
            self::$translator = $translator;
        }

        if (self::$translator === null) {
            if (self::getInstance() instanceof SnmpTranslator) {
                // try to use the SnmpEngine
                self::$translator = self::$engine;
            } else {
                self::$translator = new NetSnmp();
            }
        }

        return self::$translator;
    }

    // ---- Public Interface ----

    /**
     * @param array $device
     * @param string|array $oids single or array of oids to get
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return DataSet|OIDData collection of results
     * @throws InvalidOidFormatException
     */
    public static function get($device, $oids, $mib = null, $mib_dir = null)
    {
        if (is_string($oids) && str_contains($oids, ' ')) {
            throw new InvalidOidFormatException("Multiple OIDs must be specified in an array: $oids");
        }
        $result = SNMP::getInstance()->get($device, $oids, $mib, $mib_dir);
        return (count((array)$oids) == 1 && $result->count() == 1) ? $result->first() : $result;
    }

    /**
     * @param array $device
     * @param string|array $oids single or array of oids to walk
     * @param null $options Options to send to snmpget
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return string exact results from snmpget
     */
    public static function getRaw($device, $oids, $options = null, $mib = null, $mib_dir = null)
    {
        return SNMP::getInstance()->getRaw($device, $oids, $options, $mib, $mib_dir);
    }


    /**
     * @param array $device
     * @param string|array $oids single or array of oids to walk
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return DataSet collection of results
     * @throws InvalidOidFormatException
     */
    public static function walk($device, $oids, $mib = null, $mib_dir = null)
    {
        if (is_string($oids) && str_contains($oids, ' ')) {
            throw new InvalidOidFormatException("Multiple OIDs must be specified in an array: $oids");
        }
        return SNMP::getInstance()->walk($device, $oids, $mib, $mib_dir);
    }

    /**
     * @param array $device
     * @param string $oid single oid to walk
     * @param string $options Options to send to snmpwalk
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return string exact results from snmpwalk
     */
    public static function walkRaw($device, $oid, $options = null, $mib = null, $mib_dir = null)
    {
        return SNMP::getInstance()->walkRaw($device, $oid, $options, $mib, $mib_dir);
    }


    /**
     * @param array $device
     * @param string $oids
     * @param string $options
     * @param string $mib
     * @param string $mib_dir
     * @return string
     */
    public static function translate($device, $oids, $options = null, $mib = null, $mib_dir = null)
    {
        if (empty($oids)) {
            return $oids;
        }

        $key = Cache::genKey(__FUNCTION__, $oids, '', $options);
        $result = (array)Cache::remember($key, function () use ($device, $oids, $options, $mib, $mib_dir) {
            return SNMP::getTranslator()->translate($device, $oids, $options, $mib, $mib_dir);
        }, 86400);

        return is_array($oids) ? $result : array_shift($result);
    }

    /**
     * @param array $device
     * @param string|array $oids
     * @param string $mib
     * @param string $mib_dir
     * @return string|array
     */
    public static function translateNumeric($device, $oids, $mib = null, $mib_dir = null)
    {
        if (empty($oids)) {
            return $oids;
        }

        $key = Cache::genKey(__FUNCTION__, $oids);
        $result = (array)Cache::remember($key, function () use ($device, $oids, $mib, $mib_dir) {
            return SNMP::getTranslator()->translateNumeric($device, $oids, $mib, $mib_dir);
        }, 86400);

        return is_array($oids) ? $result : array_shift($result);
    }
}
