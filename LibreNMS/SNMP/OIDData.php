<?php
/**
 * OIDData.php
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

class OIDData extends BaseDataSet
{
    // define all properties here to ease access.
    /** @property string     oid         the full oid */
    /** @property string     name        The string name of this OID, such as sysDescr */
    /** @property string     base_oid    the base oid, the first part of the oid */
    /** @property int        index       the index for this object */
    /** @preperty array      extra_oid   any oid parts after the index, split by the . */
    /** @property string     type        the type for of the value (string, oid, integer32, etc) */
    /** @property string|int value       the value of this object */
    /** @property string     description For enums, this is the description of that value. Valid for type integer32 */
    /** @property int        seconds     the value in seconds. Valid for type timeticks and some integer32 */
    /** @preoprty string     readable    human readable time value as return by netsnmp.  Valid for type timeticks */

    /**
     * Parse raw SNMP value in Net-Snmp format.
     * Returns a fully populated OIDData object.
     *
     * @param string $oid
     * @param string $raw_value
     * @return self
     */
    public static function makeRaw($oid, $raw_value)
    {
        return Parse::rawValue($raw_value)
            ->merge(Parse::rawOID($oid));
    }

    /**
     * Parse an SNMP value of a known type.
     * Returns a fully populated OIDData object.
     *
     * @param string $oid
     * @param string $type
     * @param string $value
     * @return self
     */
    public static function makeType($oid, $type, $value)
    {
        return Parse::value($type, $value)
            ->merge(Parse::rawOID($oid));
    }

    /**
     * Generate an OIDData object for an error.
     *
     * @param int $error
     * @param null $message
     * @return self
     */
    public static function makeError($error, $message = null)
    {
        return self::make()->setError($error, $message)->put('value', null);
    }

    /**
     * Magic getter function
     * Get array values as though they were properties
     *
     * @param mixed $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }
}
