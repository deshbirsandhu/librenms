<?php
/**
 * AccessPoint.php
 *
 * Wireless Access Point connected to a controller
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

namespace LibreNMS\Device;

use LibreNMS\Interfaces\Discovery\AccessPointsDiscovery;
use LibreNMS\Interfaces\DiscoveryModule;
use LibreNMS\Interfaces\PollerModule;
use LibreNMS\OS;

class AccessPoint extends Model implements DiscoveryModule, PollerModule
{
    protected static $table = 'access_points';
    protected static $id_column = 'accesspoint_id';
    protected static $unique_columns = array('device_id', 'name', 'radio_number');
    protected static $columns = array(
        'accesspoint_id',
        'device_id',
        'name',
        'radio_number',
        'type',
        'mac_addr',
        'deleted',
        'channel',
        'txpow',
        'radioutil',
        'numasoclients',
        'nummonclients',
        'numactbssid',
        'nummonbssid',
        'interference'
    );
    protected static $excluded_update_columns = array(
        'deleted',
        'channel',
        'txpow',
        'radioutil',
        'numasocclients',
        'nummonclients',
        'numactbssid',
        'nummonbssid',
        'interference'
    );

//    private $accesspoint_id;

//    protected $name;
//    protected $device_id;
//    protected $radio_number;
//    protected $type;
//    protected $mac_addr;
//    protected $channel;
//    protected $txpow;
//    protected $radioutil;
//    protected $numasocclients;
//    protected $nummonclients;
//    protected $numactbssid;
//    protected $nummonbssid;
//    protected $interference;

    public function __construct(
        $name,
        $device_id,
        $radioNumber,
        $type,
        $macAddr,
        $channel,
        $tx_power,
        $radioutil,
        $numAssocClients,
        $numMonClients,
        $numActBssid,
        $numMonBssid,
        $interference
    ) {
        $this->name = $name;
        $this->device_id = $device_id;
        $this->radio_number = $radioNumber;
        $this->type = $type;
        $this->mac_addr = $macAddr;
        $this->channel = $channel;
        $this->txpow = $tx_power;
        $this->radioutil = $radioutil;
        $this->numasoclients = $numAssocClients;
        $this->nummonclients = $numMonClients;
        $this->numactbssid = $numActBssid;
        $this->nummonbssid = $numMonBssid;
        $this->interference = $interference;

        $this->deleted = 0;
    }

    public static function discover(OS $os)
    {
        if ($os instanceof AccessPointsDiscovery) {
            $aps = $os->discoverAccessPoints();

            $fields = array(
                'device_id' => $os->getDeviceId()
            );

            self::sync($aps, $fields);
        }
    }

    public static function poll(OS $os)
    {
        // TODO: Implement poll() method.
    }

    public function isValid()
    {
        return true;
    }
}
