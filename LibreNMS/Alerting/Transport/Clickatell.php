<?php
/**
 * Clickatell.php
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

amespace LibreNMS\Alerting\Transport;

use LibreNMS\Interfaces\Alerting\Transport;

class Clickatell implements Transport
{
    public function deliverAlert($obj, $opts)
    {
        $url = 'https://platform.clickatell.com/messages/http/send?apiKey=' . $opts['token'] . '&to=' . implode(',', $opts['to']) . '&content=' . urlencode($obj['title']);

        $curl = curl_init($url);
        set_curl_proxy($curl);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $ret  = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($code > 200) {
            if ($debug) {
                var_dump($ret);
            }
            return false;
        }
        return true;
    }
}
