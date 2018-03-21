<?php
/**
 * Pushbullet.php
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

class Pushbullet implements Transport
{
    public function deliverAlert($obj, $opts)
    {
        // Note: At this point it might be useful to iterate through $obj['contacts'] and send each of them a note ?

        $data = array("type" => "note", "title" => $obj['title'], "body" => $obj['msg']);
        $data = json_encode($data);

        $curl = curl_init('https://api.pushbullet.com/v2/pushes');
        set_curl_proxy($curl);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'Authorization: Bearer ' . $opts,
        ));

        $ret  = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($code > 201) {
            if ($debug) {
                var_dump($ret);
            }
            return 'HTTP Status code ' . $code;
        }
        return true;
    }
}
