<?php
/**
 * Irc.php
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

class Irc implements Transport
{
    public function deliverAlert($obj, $opts)
    {
        global $config;
        $f = $config['install_dir'] . "/.ircbot.alert";
        if (file_exists($f) && filetype($f) == "fifo") {
            $f = fopen($f, "w+");
            $r = fwrite($f, json_encode($obj) . "\n");
            $f = fclose($f);
            if ($r === false) {
                return false;
            } else {
                return true;
            }
        }
    }
}
