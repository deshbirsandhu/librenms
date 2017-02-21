<?php
/**
 * Parse.php
 *
 * Helpers for parsing SNMP data
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

class Parse
{
    /**
     * Parses an $oid string into the required fields
     *
     * @param string $oid
     * @return OIDData
     */
    public static function rawOID($oid)
    {
        $parts = collect(explode('.', $oid));
        if (count($parts) > 1) {
            // if the oid contains a name, index is the first thing after that
            if (str_contains($parts->first(), '::')) {
                list($mib, $name) = explode('::', $parts->first());
                return Format::oid(
                    $oid,
                    $parts->first(),
                    intval($parts[1]),
                    $parts->slice(2)->values()->map(function ($item) {
                        return trim($item, '"');
                    })->all(),
                    $mib,
                    $name
                );
            }

            // otherwise, assume index is the last item
            return Format::oid(
                $oid,
                $parts->slice(0, count($parts) - 1)->all(),
                $parts->last()
            );

        }

        // there are no segments in this oid
        return Format::oid(
            $oid,
            $oid,
            null
        );
    }

    /**
     * Determine what type of SNMP error an error message is.
     * Throw an exception if we cannot parse the error message.
     *
     * @param string $message message to parse to error code
     * @return int LibreNMS\SNMP error code
     * @throws \Exception If the error cannot be parsed
     */
    public static function errorMessage($message)
    {
        if (str_contains($message, 'Timeout: No Response from ')) {
            return SNMP::ERROR_UNREACHABLE;
        }
        throw new \Exception("Unknown error message: $message");
    }

    /**
     * Parse raw data in Net-SNMP format and return a DataSet containing populated OIDData objects.
     *
     *
     * @param string $rawData
     * @return DataSet
     */
    public static function rawOutput($rawData)
    {
        $result = array();
        $separator = "\r\n";
        $line = strtok($rawData, $separator);

        $unreachable = array(
            ': Unknown host (',
            'Timeout: No Response from '
        );
        if (str_contains($line, $unreachable)) {
            return Format::unreachable($line);
        }
        $tmp_oid = '';
        $tmp_value = '';
        while ($line !== false) {
            // if line contains =, parse oid and value, otherwise append value
            if (str_contains($line, ' = ')) {
                list($tmp_oid, $tmp_value) = explode(' = ', $line, 2);
            } else {
                $tmp_value .= "\n" . $line;
            }

            // get the next line
            $line = strtok($separator);

            // if the next line is parsable or we reached the end, append OIDData to results
            // skip invalid lines that don't contain : in the value
            if (($line === false || str_contains($line, ' = '))) {
                if ($tmp_value != 'No more variables left in this MIB View (It is past the end of the MIB tree)') {
                    $result[] = OIDData::makeRaw($tmp_oid, $tmp_value);
                }
            }
        }

        return DataSet::make($result);
    }

    /**
     * Generate OIDData from a raw value, generally in the format TYPE: value.
     * Checks for errors and returns an error OIDData if appropriate.
     *
     * @param string $raw_value
     * @return OIDData
     */
    public static function rawValue($raw_value)
    {
        if (!str_contains($raw_value, ': ')) {
            if ($raw_value == 'No Such Instance currently exists at this OID' ||
                $raw_value == 'No Such Object available on this agent at this OID') {
                return OIDData::makeError(SNMP::ERROR_NO_SUCH_OID, $raw_value);
            }
            return OIDData::makeError(SNMP::ERROR_PARSE_ERROR, $raw_value);
        }

        list($type, $value) = explode(': ', $raw_value, 2);
        return Parse::value($type, $value);
    }

    /**
     * Parse value and type.
     * Calls specific parser for type if one exists,
     * otherwise it uses the generic parser.
     *
     * @param string $type
     * @param string $value
     * @return OIDData
     */
    public static function value($type, $value)
    {

        $type = strtolower($type);
        $function = $type . 'Type';
        if (method_exists(__CLASS__, $function)) {
            return forward_static_call(array(__CLASS__, $function), $value);
        }

        return Format::generic($type, $value);
    }

    /**
     * Parse an snmprec line and return a populated OIDData object.
     *
     * @param string $entry
     * @return OIDData
     */
    public static function snmprec($entry)
    {
        list($oid, $type, $data) = explode('|', $entry, 3);
        return OIDData::makeType($oid, self::getSnmprecTypeString($type), $data);
    }

    /**
     * Internal method to convert snmprec numeric type to a string type.
     *
     * @param int|string $type
     * @return mixed
     */
    private static function getSnmprecTypeString($type)
    {
        static $types = array(
            2 => 'integer32',
            4 => 'string',
            '4x' => 'hex-string',
            5 => 'null',
            6 => 'oid',
            64 => 'ipaddress',
            65 => 'counter32',
            66 => 'gauge32',
            67 => 'timeticks',
            68 => 'opaque',
            70 => 'counter64'
        );
        return $types[$type];
    }

    /**
     * Parse and Format Integer data.
     *
     * @param string $input
     * @return OIDData
     */
    public static function integerType($input)
    {
        if (is_numeric($input)) {
            Format::integerType(intval($input));
        }

        if (preg_match('/^(.+)\(([0-9]+)\)$/', $input, $matches)) {
            $descr = $matches[1];
            $int = $matches[2];
            return Format::integerType($int, $descr);
        }

        if (preg_match('/^([0-9]+) (.+)$/', $input, $matches)) {
            $int = $matches[1];
            $descr = $matches[2];
            return Format::integerType($int, $descr);
        }

        return Format::integerType(null);
    }

    /**
     * Parse and Format String data.
     *
     * @param string $input
     * @return OIDData
     */
    public static function stringType($input)
    {
        return Format::stringType(trim(stripslashes($input), "\""));
    }

    /**
     * Parse and Format OID data.
     * Converts all OIDs to numeric.
     *
     * @param string $input
     * @return OIDData
     */
    public static function oidType($input)
    {
        return Format::oidType(SNMP::translateNumeric(null, $input));
    }

    /**
     * Parse and Format Timeticks data.
     *
     * @param string $input
     * @return OIDData
     */
    public static function timeticksType($input)
    {
        if (is_numeric($input)) {
            return Format::timeticksType($input);
        } else {
            $matched = preg_match('/\(([0-9]+)\) (.+)/', $input, $matches);
            if ($matched) {
                return Format::timeticksType($matches[1], $matches[2]);
            }
        }
        return Format::timeticksType(null);
    }
}
