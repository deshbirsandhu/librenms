<?php
/**
 * Credentials.php
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
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Interfaces\DataSource;

interface Credentials extends \Serializable
{
    /**
     * Get the type of this credential, should be a short string that matches the database field
     *
     * @return mixed
     */
    public function type();

    /**
     * Get the stored credentials as an array of data
     *
     * @return array
     */
    public function get();

    /**
     * The string output should be a string ready to be used by the data source
     * such as "-v2c -c public" for snmp v2
     *
     * @return string
     */
    public function __toString();
}
