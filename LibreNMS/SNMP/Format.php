<?php
/**
 * Format.php
 *
 * Functions to ensure proper formatting of OIDData
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

namespace LibreNMS\SNMP;

use LibreNMS\SNMP;

class Format
{

    /**
     * @param string $message
     * @return DataSet
     */
    public static function unreachable($message)
    {
        return DataSet::makeError(SNMP::ERROR_UNREACHABLE, $message);
    }

    /**
     * @param $oid
     * @param $base_oid
     * @param $index
     * @param null $extra_oid
     * @param null $mib
     * @param null $name
     * @return OIDData
     */
    public static function oid($oid, $base_oid, $index, $extra_oid = null, $mib = null, $name = null)
    {
        return OIDData::make(compact('mib', 'oid', 'base_oid', 'index', 'name', 'extra_oid'));
    }

    /**
     * @param string $type
     * @param mixed $value
     * @return OIDData
     */
    public static function generic($type, $value)
    {
        return OIDData::make(compact('type', 'value'));
    }

    /**
     * @param string $numeric_oid
     * @return OIDData
     */
    public static function oidType($numeric_oid)
    {
        $data = OIDData::make(array(
            'type' => 'oid',
            'value' => $numeric_oid,
        ));

        return $data;
    }


    /**
     * @param int $integer
     * @param string $description
     * @return OIDData
     */
    public static function integerType($integer, $description = null)
    {
        $data = array(
            'type' => 'integer',
            'value' => (int)$integer,
            'description' => $description
        );

        if ($description == 'seconds') {
            $data['seconds'] = (int)$integer;
        }

        return OIDData::make($data);
    }

    /**
     * @param string $string
     * @return OIDData
     */
    public static function stringType($string)
    {
        return OIDData::make(array(
            'type'   => 'string',
            'value'  => $string
        ));
    }

    /**
     * @param int $milliseconds
     * @param string $readable
     * @return OIDData
     */
    public static function timeticksType($milliseconds, $readable = null)
    {
        return OIDData::make(array(
            'type' => 'timeticks',
            'value' => (int)$milliseconds,
            'description' => 'milliseconds',
            'seconds' => (int)floor($milliseconds / 100),
            'readable' => $readable
        ));
    }

    /**
     * Try to change from simple oid to Module::oid format
     *
     * @param string $oid
     * @param string $mib
     * @return string
     */
    public static function compoundOid($oid, $mib)
    {
        if (!str_contains($oid, '::') && $mib !== null && !str_contains($mib, ':') && !self::isNumericOid($oid)) {
            return "$mib::$oid";
        }
        return $oid;
    }

    /**
     * Check if an OID value is numeric.
     *
     * @param string $oid
     * @return bool
     */
    public static function isNumericOid($oid)
    {
        return (bool)preg_match('/^[0-9\.]+$/', $oid);
    }

    /**
     * @param string $oid
     * @param string $hex
     * @return string
     */
    public static function hexStringAsString($oid, $hex)
    {
        // we do not understand MIBs, so approximate it...
        if (starts_with(ltrim($oid, '.'), array('1.3.6.1.2.1.4.35.1.4', 'IP-MIB::ipNetToPhysicalPhysAddress'))) {
            return mac_clean_to_readable($hex);
        }

        return hex2str($hex);
    }
}
