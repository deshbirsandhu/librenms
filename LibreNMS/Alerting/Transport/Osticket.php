<?php
/**
 * Osticket.php
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

class Osticket implements Transport
{
    public function deliverAlert($obj, $opts)
    {
        global $config;

        $url   = $opts['url'];
        $token = $opts['token'];

        foreach (parse_email($config['email_from']) as $from => $from_name) {
            $email = $from_name . ' <' . $from . '>';
            break;
        }

        $protocol = array(
            'name' => 'LibreNMS',
            'email' => $email,
            'subject' => ($obj['name'] ? $obj['name'] . ' on ' . $obj['hostname'] : $obj['title']),
            'message' => strip_tags($obj['msg']),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'attachments' => array(),
        );
        $curl     = curl_init();
        set_curl_proxy($curl);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-type' => 'application/json',
            'Expect:',
            'X-API-Key: ' . $token
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($protocol));
        $ret  = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($code != 201) {
            var_dump("osTicket returned Error, retry later");
            return false;
        }

        return true;
    }
}
