<?php
/**
 * Gitlab.php
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

class Gitlab implements Transport
{
    public function deliverAlert($obj, $opts)
    {
        // Don't create tickets for resolutions
        if ($obj['state'] == 0) {
            return true;
        } else {
            $device = device_by_id_cache($obj['device_id']); // for event logging

            $project_id  = $opts['project_id'];
            $project_key = $opts['key'];
            $details     = "Librenms alert for: " . $obj['hostname'];
            $description = $obj['msg'];
            $title       = urlencode($details);
            $desc        = urlencode($description);
            $url         = $opts['host'] . "/api/v4/projects/$project_id/issues?title=$title&description=$desc";
            $curl        = curl_init();

            $data       = array("title" => $details,
                            "description" => $description
                            );
            $postdata   = array("fields" => $data);
            $datastring = json_encode($postdata);

            set_curl_proxy($curl);

            $headers = array('Accept: application/json', 'Content-Type: application/json', 'PRIVATE-TOKEN: '.$project_key);

            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_VERBOSE, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $datastring);

            $ret  = curl_exec($curl);
            $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($code == 200) {
                $gitlabout = json_decode($ret, true);
                d_echo("Created Gitlab issue " . $gitlabout['key'] . " for " . $device);
                return true;
            } else {
                d_echo("Gitlab connection error: " . serialize($ret));
                return false;
            }
        }
    }
}
