<?php
/**
 * BaseDataSet.php
 *
 * An abstract class for shared functionality between DataSet and OIDData
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
 * @copyright  2017 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\SNMP;

use Illuminate\Support\Collection;
use LibreNMS\SNMP;

abstract class BaseDataSet extends Collection
{
    protected $error = SNMP::ERROR_NONE;
    protected $errorMessage = null;

    /**
     * Create an empty DataSet with an SNMP error code
     * likely SNMP::ERROR_UNREACHABLE
     *
     * @param int $error
     * @param null $message
     * @return static
     */
    public static function makeError($error, $message = null)
    {
        return self::make()->setError($error, $message);
    }

    /**
     * Check if this DataSet has an error
     *
     * @return bool
     */
    public function hasError()
    {
        return $this->error != SNMP::ERROR_NONE;
    }

    /**
     * Set the error values on this object
     * Only subclasses are allowed to call this
     *
     * @param int $error error code from SNMP
     * @param string $message error text
     * @return $this
     */
    protected function setError($error, $message)
    {
        $this->error = $error;
        $this->errorMessage = $message;
        return $this;
    }

    /**
     * Get the error code, see SNMP
     *
     * @return int
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Returns the error message.
     * May be null if there is no message or error.
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Merge the collection with the given items.
     * Carry over error values
     *
     * @param  mixed  $items
     * @return static
     */
    public function merge($items)
    {
        // copy over error info
        return self::make(parent::merge($items))->setError($this->error, $this->errorMessage);
    }
}
