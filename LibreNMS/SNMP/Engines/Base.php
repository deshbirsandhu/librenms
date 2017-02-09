<?php
/**
 * Base.php
 *
 * A base implementation of get() and walk(),  children need to supply getRaw() and walkRaw()
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

use LibreNMS\SNMP\Contracts\SnmpEngine;

abstract class Base implements SnmpEngine
{
    /**
     * Gets the name of this SNMP Engine
     * @return string
     */
    public function getName()
    {
        $reflectionClass = new \ReflectionClass($this);
        return $reflectionClass->getShortName();
    }

    //FIXME probably not the right place for this...
    protected function prepSetting($device, $setting)
    {
        global $config;

        if (isset($device[$setting]) && is_numeric($device[$setting]) && $device[$setting] > 0) {
            return $device[$setting];
        } elseif (isset($config['snmp'][$setting])) {
            return $config['snmp'][$setting];
        }
    }
}
