<?php
/**
 * Format.php
 *
 * -Description-
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
     * @param $message
     * @return DataSet
     */
    public static function unreachable($message)
    {
        return DataSet::makeError(SNMP::ERROR_UNREACHABLE, $message);
    }

    public static function oid($oid, $base_oid, $index, $extra_oid)
    {
        return OIDData::make(compact('oid', 'base_oid', 'index', 'extra_oid'));
    }

    /**
     * @param string $type
     * @param mixed $value
     * @return OIDData
     */
    public static function generic($type, $value)
    {
        return OIDData::make(array(
            'type' => $type,
            'value' => $value
        ));
    }

    public static function oidType($numeric_oid, $oid = null)
    {
        $data = OIDData::make(array(
            'type' => 'oid',
            'value' => $numeric_oid,
            'raw_value' => is_null($oid) ? $numeric_oid : $oid
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
     * @param $oid
     * @param $mib
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
     * @param $oid
     * @return bool
     */
    public static function isNumericOid($oid)
    {
        return (bool)preg_match('/^[0-9\.]+$/', $oid);
    }

    public static function hexStringAsString($oid, $hex)
    {
        // we do not understand MIBs, so approximate it...
        if (starts_with(ltrim($oid, '.'), '1.3.6.1.2.1.4.35.1.4')) {
            return mac_clean_to_readable($hex);
        }

        return hex2str($hex);
    }
}
