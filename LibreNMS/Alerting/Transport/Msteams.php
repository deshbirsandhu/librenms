<?php
/**
 * Msteams.php
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
namespace LibreNMS\Alerting\Transport;

use LibreNMS\Interfaces\Alerting\Transport;

class Msteams implements Transport
{
    public function deliverAlert($obj, $opts)
    {
        $url   = $opts['url'];
        $color = ($obj['state'] == 0 ? '#00FF00' : '#FF0000');
        $data  = array(
            'title' => ($obj['name'] ? $obj['name'] . ' on ' . $obj['hostname'] : $obj['title']),
            'themeColor' => $color,
            'text' => strip_tags($obj['msg'])
        );
        $curl  = curl_init();
        set_curl_proxy($curl);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-type' => 'application/json',
            'Expect:'
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        $ret  = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($code != 200) {
            var_dump("Microsoft Teams returned Error, retry later");
            return false;
        }

        return true;
    }
}
